# SECURITY AUDIT — SECOND PASS

**Proyecto:** Anuska Complementos CRM (Symfony 8.1 / PHP 8.4 / PostgreSQL 16)
**Fecha:** 2026-09-14
**Modo:** revisión completa y escéptica (no se dan por válidas las conclusiones de la 1ª pasada)
**Usuarios reales:** 3 de confianza (padres + tú). No SaaS, sin registro público, sin clientes externos.

---

## 1. Executive Summary

He vuelto a revisar **todo** el proyecto (rutas, controllers, entidades, servicios, config, plantillas,
JS propio, env, tests) sin confiar en la 1ª auditoría. Resultado: **0 CRITICAL, 0 HIGH**. Las
correcciones de la primera pasada están **presentes y funcionan**, con **una excepción parcial** (M1:
ver F-1). Aparecen algunos hallazgos **LOW/INFO** nuevos, ninguno bloqueante.

La conclusión más importante no es de código sino de **arquitectura**: **NO tiene sentido exponer este
CRM en un dominio público.** Para 3 personas conocidas, un dominio público solo añade superficie de
ataque (bots, credential stuffing, escaneo de vulnerabilidades, parcheo urgente) sin aportar nada. La
recomendación es una **red privada tipo Tailscale** (§15–16): mismo acceso desde cualquier sitio, con
la superficie de ataque de una app de red local (≈cero desde Internet).

**Estado de las correcciones de la 1ª pasada (verificado hoy):**

| Fix | Estado |
|-----|--------|
| H1 login throttling (5/min) | ✅ presente y probado |
| M1 APP_SECRET fuera del repo | 🟠 **parcial** — `.env` placeholder ✅ y prod OK, pero `.env.dev` versiona un secreto de dev que anula `.env.local` (F-1) |
| M2 cabeceras de seguridad + CSP | ✅ presente |
| M3 cookies HttpOnly/SameSite/Secure-auto | ✅ presente |
| L1 mensaje de login genérico | ✅ presente |
| L2 límite en exportaciones (10 000) | ✅ presente |
| L4 canal de log de seguridad en prod | ✅ presente |

---

## 2. Security Score

# 93 / 100

Sin CRITICAL/HIGH. Se descuentan puntos por: M1 parcial (F-1), CSV injection (F-2), MIME no validado
por contenido (F-3) y dependencias del despliegue (HTTPS/secreto/usuario BD, que el código no impone).
Sube a ~97 en cuanto se cierren F-1..F-3 y se aplique la arquitectura recomendada.

---

## 3. Vulnerabilities

### CRITICAL
Ninguna.

### HIGH
Ninguna.

### MEDIUM
Ninguna.

### LOW

**F-1 · Secreto de desarrollo versionado en `.env.dev` (anula la corrección M1 en dev)**
- **Archivo:** `.env.dev:3` — `APP_SECRET=<valor hex de 32 caracteres, redactado>`
- **Evidencia:** el orden de carga de Symfony hace que `.env.$APP_ENV` gane sobre `.env.local`.
  Verificado en ejecución: con `APP_ENV=dev` el `APP_SECRET` efectivo era el de `.env.dev`, **no** el de
  `.env.local`. Es decir, el secreto de dev que creímos "movido a `.env.local`" en la 1ª pasada está
  muerto, y hay un secreto real (aunque solo de dev) commiteado.
- **Impacto:** solo **dev** (en prod `APP_ENV=prod` no carga `.env.dev`). Firma cookies remember-me y
  tokens CSRF del entorno de desarrollo. Exposición baja, pero repite exactamente el error que M1
  pretendía cerrar.
- **Solución:** quitar la línea `APP_SECRET` de `.env.dev` (o dejar un placeholder) para que `.env.local`
  gobierne también dev. Confirmar que el `APP_SECRET` de **producción** nunca fue este valor (no lo es).

**F-2 · CSV / Formula Injection en las exportaciones**
- **Archivo:** `src/Service/CsvExporter.php:25-28` (`fputcsv` sin neutralizar celdas)
- **Evidencia:** se exportan valores introducidos por el usuario (nombre de marca, nombres de contacto,
  nº de pedido/factura). Si un valor empieza por `= + - @`, Excel/LibreOffice puede interpretarlo como
  fórmula al abrir el CSV (p. ej. un proveedor llamado `=HYPERLINK("http://malo","pincha")`).
- **Impacto:** bajo en este modelo (los 3 usuarios introducen sus propios datos), pero un CSV podría
  abrirse en otro equipo. Ejecución de fórmulas / exfiltración vía HYPERLINK/WEBSERVICE.
- **Solución:** prefijar con `'` (apóstrofo) o espacio las celdas cuyo primer carácter sea `= + - @ \t \r`.
  ~4 líneas en `CsvExporter`.

**F-3 · Subida: la extensión se valida, el MIME real no**
- **Archivo:** `src/Service/DocumentStorage.php:31-33` y `mimeFor()` (el MIME se deriva de la extensión,
  no del contenido real con fileinfo).
- **Evidencia:** un archivo con contenido arbitrario renombrado a `factura.pdf` pasa (extensión pdf) y se
  guarda con `mimeType=application/pdf`.
- **Impacto:** **bajo y ya mitigado en la práctica**: el archivo se guarda con nombre aleatorio, **fuera
  de `public/`**, y en la descarga se fuerza `Content-Type` + `X-Content-Type-Options: nosniff`, así que
  un HTML disfrazado de PDF **no se ejecuta** como HTML. Queda como endurecimiento, no como agujero.
- **Solución:** comprobar `$file->getMimeType()` (usa fileinfo, mira los bytes) contra un mapa
  extensión→MIME esperado y rechazar si no cuadra. ~6 líneas.

### INFO

- **I-1 · Ficheros huérfanos al borrar por cascade.** `Document` tiene `onDelete: CASCADE` desde sus
  propietarios (`src/Entity/Document.php:40-58`). Al borrar una **Feria** (`FairController::delete`) se
  borran sus filas Document en BD, pero los ficheros físicos en `storage/` **no** se eliminan (solo
  `DocumentController::delete` llama a `storage->remove`). Fuga de disco, no de seguridad.
- **I-2 · Config sin usar.** `MESSENGER_TRANSPORT_DSN` (doctrine) y `MAILER_DSN=null` están configurados
  pero la app no envía correo ni usa colas. Reduce superficie retirándolos si no se van a usar.
- **I-3 · Política de contraseña mínima 6.** `src/Form/UserType.php:45`. Aceptable para 3 usuarios;
  recomendable subir a 8–10 dado que no hay MFA.
- **I-4 · IDs secuenciales sin ownership por registro.** Los `*_show` y `document_download` usan IDs
  enteros predecibles y **no** comprueban propiedad por usuario. **No es una vulnerabilidad aquí**: por
  diseño los 3 usuarios están autorizados a todos los datos de negocio (ver §4). La protección de backend
  existe (firewall `ROLE_USER` en todas las rutas). Se documenta como riesgo **aceptado**.

---

## 4. Access Control Audit

Firewall `main` con `access_control` evaluado en orden (solo aplica la 1ª regla que casa):
`^/login → PUBLIC_ACCESS`, `^/admin → ROLE_ADMIN`, `^/ → ROLE_USER`. El comodín `^/` cubre **todo** lo
demás, así que no hay endpoint sin protección.

| Recurso | Anónimo | ROLE_USER | ROLE_ADMIN |
|---------|---------|-----------|------------|
| Proveedores, contactos, pedidos, facturas, comunicaciones, citas, ferias, documentos, pagos | ❌ redirige a /login | ✅ ver/crear/editar (por diseño) | ✅ |
| Exportaciones CSV | ❌ | ✅ | ✅ |
| **Usuarios** (`/admin/usuarios`) | ❌ | ❌ **bloqueado** | ✅ |
| **Categorías** (`/configuracion/categorias`) | ❌ | ❌ **bloqueado** (`#[IsGranted('ROLE_ADMIN')]`) | ✅ |

- **Anónimo:** no ve, modifica, descarga ni ejecuta **nada** salvo la página de login. ✅
- **ROLE_USER:** CRUD completo del negocio (intencionado). **No** llega a administración ni gestiona
  usuarios ni cambia roles. Doble barrera en Usuarios/Categorías: `access_control ^/admin` **y**
  `#[IsGranted('ROLE_ADMIN')]` a nivel de clase (defensa en profundidad). ✅
- **ROLE_ADMIN:** gestiona usuarios y categorías. Protegido correctamente. `toggle` impide desactivarse a
  sí mismo (`Admin/UserController.php:80`). ✅

**Voters:** no hay ni hacen falta. Un Voter tendría sentido solo si distintos usuarios debieran ver
subconjuntos distintos de datos; aquí los 3 comparten todo. Añadirlo sería complejidad sin beneficio.

---

## 5. Upload Security Audit

| Comprobación | Estado |
|---|---|
| Allowlist de extensiones (`pdf,jpg,jpeg,png,doc,docx,xls,xlsx`) | ✅ `DocumentStorage.php:17` |
| `.php/.phtml/.phar/.cgi/.htaccess` | ✅ **no** en la lista → rechazados |
| Doble extensión `factura.pdf.php` | ✅ `getClientOriginalExtension()` toma la última (`php`) → rechazada |
| `malware.php` renombrado a `factura.pdf` | ✅ se guarda como `<random>.pdf`, **no ejecutable** |
| MIME real por contenido | 🟠 **no** (F-3), mitigado por nosniff + Content-Type forzado |
| Límite de tamaño (10 MB) | ✅ `MAX_BYTES` |
| Nombre en disco | ✅ `bin2hex(random_bytes(16))` — no se usa el nombre del usuario como path |
| Path traversal (`../`, `\..\`, URL-encoded) | ✅ imposible: la ruta se compone de `date()` + nombre aleatorio, nunca de entrada del usuario |
| **SVG** | ✅ **no permitido** → sin riesgo de XSS por SVG |
| **HTML** | ✅ **no permitido**; y aunque se colara, se sirve con Content-Type forzado + nosniff |
| **ZIP** | ✅ **no permitido** → sin Zip Slip ni bombas de descompresión |
| Ejecución en servidor | ✅ `storage/` está fuera de `public/`; Nginx no ejecuta PHP ahí |

Cobertura de tests: `DocumentTest::testRejectsDisallowedExtension` (rechaza `.exe`) y
`testUploadDownloadDelete` (flujo válido). ✅

---

## 6. Document Download Audit

Ruta `GET /documentos/{id}` (`requirements: id => \d+`), resuelta por EntityValueResolver.

- **Autenticación:** ✅ bajo firewall `^/` → `ROLE_USER`. Un anónimo es redirigido a /login.
- **Autorización:** todos los autenticados pueden descargar cualquier documento — **aceptado por diseño**
  (§4, I-4). No es un fallo en este modelo de 3 usuarios.
- **Path traversal / acceso directo:** ✅ la ruta física sale de `document.storagePath` (BD, con hash),
  no de la URL. No hay `/uploads` público (§8).
- **Nombres predecibles / enumeración:** IDs secuenciales, pero solo accesibles autenticado. Aceptado.
- **Content-Type / Disposition:** ✅ `Content-Type` del documento + `nosniff`; PDF/imagen `inline`, el
  resto `attachment` (`DocumentController.php:74-80`). El nombre original se sanea vía `HeaderUtils`.

**Recomendación:** añadir un test funcional que confirme que un **anónimo** recibe redirección al pedir
`/documentos/{id}` (hoy se cubre indirectamente por el firewall, pero conviene test explícito).

---

## 7. Authentication Audit

- `form_login` con CSRF (`enable_csrf: true`), token `authenticate` en la plantilla. ✅
- `UserChecker` rechaza inactivos con **mensaje genérico** (anti-enumeración). ✅
- **Throttling** 5 intentos/min por IP+usuario. ✅ (test `SecurityHardeningTest`)
- `remember_me` firmado con `%kernel.secret%`, 1 semana, sin tabla (cookie firmada). ✅
- Hash de contraseña `auto` (bcrypt/argon). ✅
- Sesión: `httponly`, `samesite=lax`, `secure=auto`, caduca al cerrar el navegador. ✅
- `getRoles()` garantiza `ROLE_USER`; `setRoles` solo se invoca desde el form de admin. ✅

---

## 8. CSRF Audit

- **14** acciones POST no-formulario con `isCsrfTokenValid` y token ligado al ID del recurso
  (toggle, delete, resolve, set-status, upload, payment, primary…). ✅
- Acciones con **Symfony Form** (crear/editar de todas las entidades): CSRF del componente Form activo
  por defecto; ningún form desactiva `csrf_protection`. ✅
- **Todas las acciones destructivas son POST** (ver §4 / router): no hay borrado ni cambio de estado por
  GET. ✅

Falta cubrir con test: rechazo explícito de un POST destructivo **sin** token (recomendado, §18).

## 9. XSS Audit

- Twig con autoescape por defecto; **sin `|raw`** en ninguna plantilla. ✅
- **Sin** `innerHTML`/`insertAdjacentHTML`/`document.write`/`eval` en JS propio (solo en vendor
  Turbo/Stimulus, que no inyecta datos del usuario). ✅
- XSS **almacenado**: nombres/asuntos/notas se renderizan escapados por Twig; los documentos se sirven
  con nosniff. ✅

## 10. SQL Injection Audit

- Todo Doctrine ORM parametrizado. Buscador global (`GlobalSearch`): DQL con `WHERE`/`ORDER BY`
  **constantes en código** y valor por `:q` parametrizado. ✅
- Ordenación en listados: whitelist (`brandName`, `status`, etc.), no se interpola entrada del usuario. ✅
- **Sin SQL nativo** en `src/`. ✅

## 11. Dependency Audit

- `composer audit`: **No security vulnerability advisories found** (verificado hoy). ✅
- **Sin npm/Node** (AssetMapper) → sin superficie de dependencias JS. ✅
- Profiler / debug-bundle / maker / fixtures: **solo dev+test** (`config/bundles.php`, `require-dev`) →
  no se instalan con `composer install --no-dev`. ✅
- Sugerencia (no urgente): revisar si Messenger/Mailer se van a usar; si no, retirarlos (I-2).

## 12. Production Configuration Audit

- `.env` commiteado trae `APP_ENV=dev` (correcto: prod se fija en `.env.local`/variable de entorno). ✅
- Profiler y debug toolbar **no** existen en prod. ✅
- Errores: en prod Symfony no muestra stack traces (no hay debug). ✅
- Cabeceras y HSTS (bajo HTTPS): ✅ vía `SecurityHeadersListener`.
- **Pendiente de despliegue (no código):** `APP_ENV=prod`, `APP_DEBUG=0`, HTTPS forzado. Documentado en
  `docs/deploy.md`.

## 13. Secrets Audit

- `.env`: `APP_SECRET` = placeholder. ✅
- `.env.local`: gitignored, secreto real de 64 hex. ✅
- `.env.dev`: 🟠 **contiene un secreto real de dev** (F-1) → limpiar.
- `.env.test`: secreto dummy de test (estándar). ✅
- Sin API keys / credenciales SMTP / tokens en el código. `DATABASE_URL` de `.env` apunta a `postgres`
  local sin contraseña (solo default de dev; prod usa usuario `anuska` dedicado, `deploy.md`). ✅
- **Rotación:** el secreto de `.env.dev` conviene eliminarlo; no requiere rotar el de producción (nunca
  se expuso: prod usa uno nuevo generado en despliegue).

## 14. Backup & Data Protection

Datos sensibles del CRM: nombres, emails, teléfonos, datos fiscales, facturas y documentos. **No** se
envían a terceros (Mailer null, sin analytics, sin CDNs externos salvo las tipografías/CDN permitidas
del propio front). Logs en prod (`monolog`) van a `stderr` en JSON e **no** registran contraseñas,
tokens ni cuerpos de documentos (solo eventos de seguridad y errores). ✅

**Estrategia de backup sencilla (3 usuarios), sin nada enterprise:**
1. Cron **diario** en la máquina del CRM: `pg_dump` de la BD + `tar` de `storage/` a una carpeta
   `backups/` que **no** esté en `public/` ni en Git (`.gitignore` ya excluye `/storage/`; añadir
   `/backups/`).
2. Permisos restrictivos: `chmod 700 backups/`, propiedad del usuario del servicio.
3. Copia **fuera de la máquina**: a un disco USB/NAS de la tienda (o, si más adelante lo autorizas, a un
   almacenamiento en la nube que tú controles, cifrando el `.tar`).
4. Retención: conservar 7–14 copias diarias, borrar las más antiguas.

Backups y base física de documentos deben respaldarse **juntos** (una factura es fila en BD + fichero en
`storage/`).

---

## 15. Deployment Architecture Analysis

| Opción | Seguridad | Complejidad | Coste | Acceso remoto | Recomendación |
|--------|-----------|-------------|-------|---------------|---------------|
| **A** Dominio público + HTTPS (VPS) | 🔴 Baja: expuesto a todo Internet (bots, credential stuffing, escaneo). Exige parcheo rápido y vigilancia | Media-alta (TLS, hardening, monitorización) | ~5 €/mes VPS + ~10 €/año dominio | ✅ desde cualquier sitio | ❌ |
| **B** Solo red local (PC/servidor en la tienda) | 🟢 Alta: sin exposición a Internet | Baja | Casi 0 (hardware ya existente) | ❌ solo en la tienda | ⚠️ parcial |
| **C** VPN clásica (WireGuard/OpenVPN self-host) | 🟢 Alta | Media-alta (port-forwarding, gestión de claves) | Bajo | ✅ | ⚠️ fricción |
| **D** Red privada Zero-Trust (**Tailscale** / Cloudflare Tunnel+Access) | 🟢 Alta: **sin puertos abiertos ni DNS público** | **Baja** | **0 €** (plan personal) | ✅ desde cualquier sitio | ✅ **recomendada** |
| **E** VPS + dominio + auth | 🔴 = A | Alta | Mayor | ✅ | ❌ |

**Lectura crítica:** A y E exponen la app a Internet para beneficiar a **cero** usuarios externos. El
único motivo para elegirlas sería "es una app web y así se despliega Symfony" — que es justo lo que
pediste evitar. B es muy segura pero mata el acceso remoto (y sí necesitas multi-dispositivo/multi-sitio).
C consigue el objetivo pero con fricción para usuarios no técnicos. **D logra el acceso remoto de A con
la superficie de ataque de B**, y con la operativa más simple.

## 16. Recommended Deployment

# ✅ Opción D — Red privada tipo **Tailscale** (la app NUNCA se expone a Internet)

**Montaje:**
1. El CRM corre en **una máquina siempre encendida** (un mini-PC/NAS en la tienda, o incluso un VPS pero
   escuchando **solo** en la interfaz de la red privada, sin IP pública).
2. Instalas **Tailscale** en esa máquina y en los 3 dispositivos (móvil/portátil de tus padres y el tuyo).
   Cada uno inicia sesión **una vez** (con Google, por ejemplo) y el dispositivo queda unido a tu red
   privada de forma permanente.
3. El CRM es accesible en un nombre privado (p. ej. `http://crm-tienda:8000` vía MagicDNS) **desde
   cualquier lugar**, como si todos estuvieran en la misma LAN.

**Por qué encaja aquí:**
- **Superficie de ataque ≈ 0 desde Internet:** sin puertos abiertos, sin DNS público, nada que los bots
  puedan encontrar o atacar por fuerza bruta. El escenario de "atacante externo con solo la URL"
  **desaparece**: no hay URL pública.
- **Fácil para tus padres:** se instala una vez y queda conectado en segundo plano (más simple que
  "acuérdate de conectar la VPN").
- **0 € y bajo mantenimiento:** plan personal gratuito cubre de sobra 3 usuarios. Tráfico cifrado por
  WireGuard; sin gestionar certificados ni cortafuegos públicos.
- **Toda la protección de la app se mantiene** como defensa en profundidad (throttling, cabeceras,
  cookies, hashing): si un dispositivo autorizado se pierde, sigue haciendo falta la contraseña.

**Alternativa sin instalar nada en el cliente:** Cloudflare **Tunnel + Access** con login por Google/OTP.
La app tampoco queda expuesta directamente; Cloudflare pone una puerta de identidad **antes** de llegar a
Symfony. Un poco más de configuración inicial; útil si prefieres acceso solo-navegador.

**Lo que NO necesitas** (complejidad desproporcionada para 3 usuarios): dominio público, VPS con IP
pública, WAF, fail2ban, DDoS, orquestadores, IAM enterprise, MFA obligatorio.

---

## 17. Production Blockers

Nada de código bloquea el despliegue (0 CRITICAL/HIGH). Los únicos "must" son de **operación**:

1. Desplegar en la red privada (§16) **o**, si se optara por público, forzar HTTPS antes de exponer.
2. `APP_ENV=prod`, `APP_SECRET` nuevo en `.env.local`/variable de entorno (no el de `.env.dev`).
3. Usuario de BD `anuska` dedicado (no `postgres`).
4. Backups configurados (§14).

## 18. Final Checklist

### 🔴 BLOCKER
- (ninguno de código)

### 🟠 IMPORTANT
- [ ] Elegir arquitectura privada (§16) en lugar de dominio público.
- [ ] F-1: quitar `APP_SECRET` de `.env.dev` (deja que lo gobierne `.env.local`).
- [ ] Despliegue: `APP_ENV=prod`, secreto nuevo, usuario BD dedicado, HTTPS si hubiera exposición.
- [ ] Backups diarios BD + `storage/`, fuera de `public/` y de Git.

### 🟡 RECOMMENDED
- [ ] F-2: neutralizar celdas de fórmula en `CsvExporter`.
- [ ] F-3: validar MIME real (fileinfo) contra la extensión en la subida.
- [ ] Subir mínimo de contraseña a 8–10 (I-3).
- [ ] Tests de seguridad extra: ROLE_USER→/admin = 403; POST destructivo sin CSRF = rechazado; descarga
      anónima = redirige.
- [ ] Limpiar `storage/` huérfano al borrar por cascade (I-1); retirar Messenger/Mailer si no se usan (I-2).

### 🟢 OK
- [x] Sin CRITICAL/HIGH · Auth obligatoria en toda ruta · Admin doblemente protegido
- [x] CSRF en todas las acciones sensibles · Sin borrados por GET
- [x] Subidas: allowlist, sin SVG/HTML/ZIP/PHP, tamaño máx, nombre aleatorio, fuera de webroot
- [x] Descargas autenticadas, sin path traversal · Twig autoescape, sin |raw · Doctrine parametrizado
- [x] `composer audit` limpio · Profiler dev-only · Cabeceras + cookies endurecidas
- [x] `.env` sin secretos reales · `.env.local` gitignored · Tests en verde (48/48)

---

## 19. Final Recommendation

# 🟠 READY WITH ACCEPTED RISKS

No hay vulnerabilidades **CRITICAL** ni **HIGH** sin mitigar, por lo que el CRM es desplegable. Se marca
🟠 (y no 🟢) porque quedan hallazgos **LOW** por corregir (F-1, F-2, F-3 — esta pasada es solo informe,
sin cambios) y porque el despliegue seguro depende de aplicar la **arquitectura privada** recomendada y
los pasos de operación (§17). Ninguno es un agujero grave; cerrados F-1..F-3 y desplegado según §16, el
proyecto pasa a 🟢.

**Sobre la pregunta central (¿dominio público?):** **No.** Para 3 personas de confianza, exponerlo a
Internet solo añade riesgo sin beneficio. Recomiendo una **red privada tipo Tailscale**: mismo acceso
cómodo desde cualquier dispositivo/lugar, sin abrir el CRM al mundo. Es la combinación más equilibrada de
**seguridad + simplicidad + accesibilidad + coste + mantenimiento** para este caso.
