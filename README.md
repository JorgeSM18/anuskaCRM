# Anuska Complementos — CRM

CRM web interno para la gestión de la tienda de complementos: centraliza **proveedores** y todo lo que
cuelga de ellos (contactos y comerciales, pedidos, facturas y pagos, comunicaciones, citas, ferias,
documentos y recordatorios). Aplicación **privada** para uso interno (3 personas), no es un SaaS público.

Color de marca: `#3d1f00`.

## Stack

- **PHP 8.4** · **Symfony 8.1**
- **SQLite** (un archivo, `var/data.db`; sin servicio de BD) · Doctrine ORM 3
- **Twig** + **AssetMapper** (sin Node/npm) + Turbo/Stimulus + Sass
- Tests: **PHPUnit** · Análisis: **PHPStan** (nivel 8) · Estilo: **php-cs-fixer** (`@Symfony`)

## Requisitos

- PHP 8.4 con las extensiones: `pdo_sqlite`, `intl`, `mbstring`, `openssl`, `ctype`, `iconv`,
  `bcmath`, `zip`, `fileinfo`.
- Composer.
- (Opcional en local) Symfony CLI para `symfony serve`.

## Puesta en marcha (local)

```bash
composer install
```

Crea `.env.local` (no se versiona) con un secreto propio:

```dotenv
APP_SECRET=<genera uno: php -r "echo bin2hex(random_bytes(32));">
```

Crea el esquema y el primer usuario administrador:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:user:create --email="tu@correo.com" --name="Tu Nombre" --admin
```

Arranca el servidor de desarrollo:

```bash
symfony serve -d
```

(o `php -S localhost:8000 -t public` si no usas la Symfony CLI). Entra en `http://localhost:8000`.

> La base de datos SQLite (`var/data.db`) se crea sola al migrar. Los demás usuarios se gestionan desde
> **Configuración → Usuarios** dentro de la app.

## Comandos útiles

```bash
composer test        # PHPUnit
composer phpstan     # análisis estático (nivel 8)
composer cs          # comprobar estilo (dry-run)
composer cs-fix      # corregir estilo
composer check       # cs + phpstan + test
```

Los tests usan su propia BD SQLite aislada (`var/data_test.db`); crear su esquema una vez con
`php bin/console doctrine:migrations:migrate --env=test --no-interaction`.

## Estructura

```
src/
  Controller/   controladores (finos; delegan en servicios)
  Entity/       entidades Doctrine
  Repository/   consultas
  Service/      lógica (búsqueda global, exportación CSV, cálculo de facturas, almacenamiento de documentos…)
  Form/  Twig/  EventListener/  Security/
templates/      vistas Twig
migrations/     migraciones de esquema
storage/        documentos subidos (fuera de public/, no versionado)
docs/           despliegue y auditorías de seguridad
```

## Despliegue

- Gratis y privado (sin dominio público), paso a paso:
  [docs/deploy-gratis-oracle-tailscale.md](docs/deploy-gratis-oracle-tailscale.md)
- Guía general de servidor: [docs/deploy.md](docs/deploy.md)

Los secretos (`APP_SECRET`, etc.) viven en `.env.local` / variables de entorno, **nunca** en el repo.

## Seguridad

Auditorías y estado en [docs/security-audit.md](docs/security-audit.md) y
[docs/security-audit-2.md](docs/security-audit-2.md). Resumen: autenticación obligatoria, throttling de
login, CSRF en todas las acciones sensibles, cabeceras de seguridad + CSP, subidas restringidas y fuera
del web root, descargas autenticadas.

## Copias de seguridad

Respaldar **juntos** `var/data.db` y la carpeta `storage/`. Ver la sección de backups en las guías de
`docs/`.
