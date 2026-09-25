# Despliegue — Anuska Complementos CRM

Guía para poner el CRM en un servidor/VPS (Linux). La base de datos es **SQLite**
(un archivo, sin servicio aparte); en producción, PHP-FPM + Nginx.

> Para un despliegue **gratis y privado** (sin dominio público) paso a paso, ver
> [deploy-gratis-oracle-tailscale.md](deploy-gratis-oracle-tailscale.md).

## 1. Requisitos del servidor

- PHP 8.4+ (mismo o superior al de desarrollo) con las extensiones:
  `pdo_sqlite`, `intl`, `mbstring`, `openssl`, `ctype`, `iconv`, `bcmath`, `zip`, `fileinfo`.
- Nginx (o Apache) + PHP-FPM.
- Composer.

## 2. Preparar el código

```bash
git clone <repo> /var/www/anuska
cd /var/www/anuska
composer install --no-dev --optimize-autoloader
```

## 3. Configuración (`.env.local`, NO se commitea)

Crear `/var/www/anuska/.env.local`:

```dotenv
APP_ENV=prod
APP_SECRET=<generar NUEVO: `php -r "echo bin2hex(random_bytes(32));"`>
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

> **Importante:** el `APP_SECRET` de producción debe ser **nuevo y único** (nunca el de desarrollo).
> Firma las cookies "recordarme" y los tokens CSRF. El `.env` versionado solo lleva un
> placeholder; el valor real vive aquí (o en variable de entorno del servidor).

La base de datos SQLite (`var/data.db`) se crea sola al aplicar las migraciones (punto 4). No hay
servidor de BD ni usuario/rol que crear.

## 4. Base de datos y assets

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console asset-map:compile
php bin/console cache:clear --env=prod
```

> La base arranca **vacía** en producción. **No** ejecutes `doctrine:fixtures:load`
> (son datos de demo y purgan la base). Crea el primer usuario con el comando del punto 6.

## 5. Almacenamiento de documentos

Los archivos subidos se guardan en `storage/` (fuera de `public/`). Debe existir y
ser escribible por el usuario de PHP-FPM (p.ej. `www-data`):

```bash
mkdir -p storage && chown -R www-data:www-data storage var
```

## 6. Primer usuario administrador

```bash
php bin/console app:user:create --email="ana@tienda.com" --name="Ana" --admin
```

(pedirá la contraseña de forma oculta). Después, el resto de usuarios se gestionan
desde **Configuración → Usuarios** dentro de la app.

## 7. Nginx (ejemplo)

```nginx
server {
    listen 80;
    server_name crm.tutienda.com;
    root /var/www/anuska/public;

    location / { try_files $uri /index.php$is_args$args; }
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }
    location ~ \.php$ { return 404; }
}
```

Poner HTTPS con Let's Encrypt (`certbot`). La app es **privada**: no hay registro
público, todo requiere iniciar sesión.

### Seguridad (qué es automático y qué toca al servidor)

Ya viene resuelto en la aplicación:
- **Cabeceras de seguridad** (CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy)
  se añaden solas en cada respuesta. La cabecera **HSTS** se activa automáticamente cuando la
  petición llega por HTTPS.
- **Anti fuerza bruta** en el login (5 intentos/min por IP+usuario).
- Cookies **HttpOnly + SameSite=Lax + Secure** (esta última, automática bajo HTTPS).
- Documentos servidos solo tras iniciar sesión, fuera del web root.

Toca configurarlo en el servidor:
- **Forzar HTTPS**: redirección 80→443 en Nginx (certbot la crea).
- Si la app va **detrás de un proxy** (Nginx que termina TLS), añade en `.env.local`
  `trusted_proxies` para que Symfony detecte HTTPS correctamente:
  `# config/packages/framework.yaml -> framework.trusted_proxies: '127.0.0.1'` (o el rango del proxy).
- Mantener `APP_ENV=prod` y `APP_DEBUG=0` (sin profiler ni trazas).

## 8. Copias de seguridad (IMPORTANTE)

Respaldar **juntos** el archivo de base de datos y la carpeta de documentos:

```bash
sqlite3 var/data.db ".backup 'backup-$(date +%F).db'"
tar czf storage-$(date +%F).tar.gz storage/
```

Automatizar con un cron diario. Sin la carpeta `storage/`, las facturas y documentos
subidos se perderían aunque tengas la copia de la base.

## 9. Actualizaciones

```bash
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console asset-map:compile
php bin/console cache:clear --env=prod
```
