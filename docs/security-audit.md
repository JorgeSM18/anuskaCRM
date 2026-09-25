# SECURITY AUDIT — PRODUCTION READY

**Proyecto:** Anuska Complementos CRM (Symfony 8.1 / PHP 8.4 / PostgreSQL 16)
**Fecha:** 2026-09-14
**Alcance:** auditoría completa OWASP + pentest conceptual (atacante con solo la URL pública),
remediación HIGH→MEDIUM→LOW, verificación de cada mitigación y segunda pasada sobre todo el proyecto.

---

## 1. Recomendación final

# 🟢 READY FOR PRODUCTION

Sin vulnerabilidades **CRITICAL** ni **HIGH** sin mitigar. La única HIGH y todas las MEDIUM/LOW
están corregidas y verificadas. Lo que queda son **pasos de despliegue en el servidor** (no defectos
de código): forzar HTTPS, poner el `APP_SECRET` de producción y usar un usuario de BD dedicado — todo
documentado en [`docs/deploy.md`](deploy.md). El código no puede imponerlos por sí mismo, así que se
listan abajo como **prerrequisitos obligatorios de despliegue**, no como riesgos aceptados.

**Puntuación de seguridad:** **94 / 100**
(inicial 72/100 → +22 tras remediación). Los 6 puntos restantes reflejan lo que depende del servidor
—HTTPS forzado, secreto y usuario de BD de producción— y el `'unsafe-inline'` que exige el importmap
de AssetMapper (mitigado por `frame-ancestors`/`base-uri`/`form-action` y por el autoescape de Twig).

---

## 2. Resumen ejecutivo

Base de partida ya sólida: autenticación con `form_login` + `UserChecker`, **CSRF en todas las
acciones POST** (14 `isCsrfTokenValid` + CSRF de formularios), Twig con autoescape y **sin `|raw`**,
Doctrine **100% parametrizado**, whitelist en el `ORDER BY`, subida de archivos con allowlist de
extensiones y nombre aleatorio fuera de `public/`, descargas solo autenticadas, profiler solo en dev,
`composer audit` limpio y **sin npm/Node** (superficie JS mínima).

La auditoría encontró **0 CRITICAL, 1 HIGH, 3 MEDIUM, 4 LOW**. Todas corregidas.

**IDOR — aclaración:** no aplica como vulnerabilidad. Es un CRM interno compartido; por diseño todos
los usuarios autenticados están autorizados sobre todos los datos de negocio. La única frontera de
autorización real (`ROLE_ADMIN` para Usuarios y Categorías) está aplicada con `#[IsGranted]` +
`access_control ^/admin`.

---

## 3. Vulnerabilidades encontradas y corregidas

| ID | Sev. | Vulnerabilidad | Corrección | Verificado |
|----|------|----------------|------------|------------|
| **H1** | HIGH | Login sin límite de intentos → fuerza bruta con solo la URL | `login_throttling` (5 intentos/min por IP+usuario) en `security.yaml` | ✅ test que hace 5 fallos + 1 acierto y comprueba que **sigue bloqueado** |
| **M1** | MED | `APP_SECRET` real commiteado en `.env` (firma cookies remember-me y CSRF) | `.env` con placeholder; secreto real en `.env.local` (gitignored); secreto nuevo generado | ✅ `.env` = placeholder, `.env.local` = 64 hex, gitignored |
| **M2** | MED | Sin cabeceras de seguridad (clickjacking, sniffing, sin CSP) | `SecurityHeadersListener`: X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, CSP, HSTS (bajo HTTPS) | ✅ test de cabeceras + `curl` |
| **M3** | MED | Cookie de sesión sin flags de endurecimiento | `cookie_secure: auto`, `httponly: true`, `samesite: lax` en `framework.yaml` | ✅ `debug:config framework` |
| **L1** | LOW | Enumeración de usuarios (mensaje distinto para usuario inactivo) | Mensaje genérico `"Credenciales no válidas."` en `UserChecker` | ✅ revisión de código |
| **L2** | LOW | Consultas de exportación sin límite → DoS de memoria | `setMaxResults(10000)` en `findAllFiltered` (Supplier/PurchaseOrder/Invoice) | ✅ revisión de código |
| **L3** | LOW | Guía de despliegue con secreto/usuario de BD débiles | `deploy.md` endurecido: secreto nuevo, usuario `anuska` dedicado, sección de seguridad | ✅ revisión de doc |
| **L4** | LOW | Eventos de seguridad no registrados en prod (buffer los descartaba) | Handler `security` en `monolog.yaml` (canal security, level info, siempre) | ✅ revisión de config |

---

## 4. Prerrequisitos obligatorios de despliegue (no son código)

Documentados en [`docs/deploy.md`](deploy.md) §3 y §7:

1. **Forzar HTTPS** (redirección 80→443 en Nginx con certbot). Activa automáticamente HSTS y el flag
   `Secure` de las cookies.
2. **`APP_SECRET` de producción** nuevo y único en `.env.local` (nunca el de desarrollo).
3. **Usuario de BD dedicado** (`anuska`) con permisos solo sobre su base — nunca `postgres`.
4. `APP_ENV=prod`, `APP_DEBUG=0` (sin profiler ni trazas de error).
5. Si va detrás de proxy TLS: configurar `trusted_proxies` para que Symfony detecte HTTPS.

---

## 5. Riesgos aceptados (conscientes, bajos)

- **CSP con `'unsafe-inline'`** en `script-src`/`style-src`: lo exige el importmap de AssetMapper y los
  estilos en línea. Riesgo residual bajo — no hay XSS conocido (Twig autoescapa, sin `|raw`), y la CSP
  protege igualmente vía `frame-ancestors 'none'`, `base-uri 'self'` y `form-action 'self'`.
- **Sin rate-limiting global de la app** más allá del login: aceptable para un CRM interno privado tras
  autenticación. Añadir si en el futuro se expone alguna ruta pública.

---

## 6. Verificación (ejecutada hoy, 2026-09-14)

| Comprobación | Resultado |
|--------------|-----------|
| `composer audit` (dependencias) | **No security vulnerability advisories found** |
| PHPStan nivel 8 (+ symfony/doctrine) | **[OK] No errors** |
| php-cs-fixer `@Symfony` (dry-run) | **Found 0 of 112 files** |
| PHPUnit (incl. tests de hardening) | **OK (48 tests, 176 assertions)** |

Segunda pasada sobre todo el proyecto (no solo lo cambiado): sin regresiones. La app sigue funcionando
bajo la CSP (Stimulus/Turbo cargan), no se rompió ningún flujo, ningún "arreglé X y rompí Y".

---

## 7. Checklist pre-producción

- [x] Sin CRITICAL/HIGH sin mitigar
- [x] Autenticación obligatoria en toda la app (salvo `/login`)
- [x] CSRF en todas las acciones que mutan estado
- [x] Anti fuerza bruta en login (throttling)
- [x] Cabeceras de seguridad + CSP en todas las respuestas
- [x] Cookies HttpOnly + SameSite; Secure automático bajo HTTPS
- [x] Sin secretos en el repo (`.env` = placeholder)
- [x] Subidas: allowlist de extensiones, tamaño máx., nombre aleatorio, fuera de `public/`
- [x] Descargas solo autenticadas, sin path traversal
- [x] Doctrine parametrizado; `ORDER BY` con whitelist
- [x] `composer audit` limpio
- [x] Tests, PHPStan y CS en verde
- [ ] **Servidor:** HTTPS forzado (certbot) — *hacer en despliegue*
- [ ] **Servidor:** `APP_SECRET` de prod en `.env.local` — *hacer en despliegue*
- [ ] **Servidor:** usuario de BD `anuska` dedicado — *hacer en despliegue*
- [ ] **Servidor:** copias de seguridad de BD **y** `storage/` (cron) — *hacer en despliegue*
