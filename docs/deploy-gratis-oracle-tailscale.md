# Despliegue GRATIS y privado — Oracle Cloud + Tailscale

Guía paso a paso para poner el CRM **gratis, siempre encendido y sin exponerlo a Internet**:
una máquina en la nube gratuita de Oracle, accesible **solo** por tu red privada Tailscale.

**Resultado final:** tus padres y tú abrís una dirección tipo
`https://crm.tu-tailnet.ts.net` desde el móvil o el portátil, **desde cualquier lugar**, como si
estuvierais en la misma red local. No hay dominio público, no hay puertos abiertos, no hay servidor
que mantener en la tienda. Coste: **0 €**.

---

## Visión general (qué montamos y por qué es seguro)

```
   Tus padres / tú                      Nube gratis de Oracle
  ┌───────────────┐   red privada      ┌────────────────────────────┐
  │ Móvil/portátil│   cifrada           │  VM Ubuntu (Always Free)   │
  │  + Tailscale  │◀──── Tailscale ────▶│  Nginx → PHP → SQLite      │
  └───────────────┘   (WireGuard)       │  CRM (solo en la tailnet)  │
                                         └────────────────────────────┘
        ▲                                         ▲
        │                                         │
   Sin la app Tailscale                    Sin puertos públicos:
   NADIE llega aquí.                        Internet no la ve.
```

La clave de seguridad: **el CRM no escucha en Internet**. Solo es accesible dentro de tu red Tailscale.
Un atacante externo no tiene ni URL ni puerto que atacar. Toda la protección de la app (login,
throttling, cabeceras) queda como defensa extra.

---

## Requisitos previos

- Una cuenta de correo (Google sirve) para Oracle y para Tailscale.
- Una tarjeta **solo para verificar** la cuenta de Oracle (el plan *Always Free* **no cobra**).
- 30–45 minutos la primera vez. Después casi no se toca.

---

## PARTE A — Crear la máquina gratis en Oracle Cloud

### 1. Crear la cuenta
1. Entra en `https://www.oracle.com/cloud/free/` → **Start for free**.
2. Elige tu país y crea la cuenta. Verifican con tarjeta (sin cargo en Always Free).
3. Elige una *Home Region* cercana (p. ej. **Spain Central (Madrid)** o **Germany Central (Frankfurt)**).

### 2. Crear la VM (Compute Instance)
1. Menú ☰ → **Compute → Instances → Create instance**.
2. **Name:** `crm-anuska`.
3. **Image and shape → Edit:**
   - **Image:** Canonical **Ubuntu 24.04** (o 22.04).
   - **Shape:** pestaña **Ampere (ARM)** → `VM.Standard.A1.Flex`. Pon **1 OCPU** y **6 GB RAM**
     (dentro del Always Free; sobra para 3 usuarios). *(Si no hay ARM disponible, usa la AMD
     `VM.Standard.E2.1.Micro`, también Always Free.)*
4. **Networking:** deja la VCN nueva por defecto. **Asigna IP pública** (la usarás solo para el primer
   acceso SSH; luego la cerramos).
5. **Add SSH keys:** sube tu clave pública (o deja que genere una y **descarga la privada**). En Windows
   puedes generar una con `ssh-keygen` en PowerShell; guarda la clave privada.
6. **Create.** En 1–2 min la instancia estará *Running*. Apunta su **IP pública**.

> **Nota Always Free:** mantén la VM modesta (1 OCPU/6 GB). Así entra de sobra en el plan gratuito y
> evitas que Oracle la reclame por capacidad.

### 3. Primer acceso SSH
Desde tu PC (PowerShell), con el usuario `ubuntu`:

```bash
ssh -i ruta/a/tu-clave-privada ubuntu@LA_IP_PUBLICA
```

Actualiza el sistema:

```bash
sudo apt update && sudo apt -y upgrade
```

---

## PARTE B — Instalar Tailscale (la red privada)

### 4. En la VM
```bash
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up --ssh
```

Te dará una URL: ábrela en tu navegador e inicia sesión (con Google, por ejemplo). La VM se une a tu red
Tailscale. El flag `--ssh` te permite entrar por SSH **a través de Tailscale** (sin puerto 22 público).

Apunta el nombre/hostname que Tailscale asigna a la VM (algo como `crm-anuska`). Su nombre completo será
`crm-anuska.tu-tailnet.ts.net`.

### 5. Activar HTTPS en tu tailnet (una vez)
En el panel web de Tailscale (`https://login.tailscale.com/admin/dns`):
- Activa **MagicDNS**.
- Activa **HTTPS Certificates**.

Esto permite que la VM tenga un certificado HTTPS válido dentro de la red privada (sin comprar nada).

---

## PARTE C — Instalar el CRM en la VM

### 6. Dependencias (PHP, Nginx, Git, Composer)
La base de datos es **SQLite** (un archivo, sin servicio que instalar ni mantener).

```bash
sudo apt -y install software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt -y install nginx git unzip \
  php8.4-fpm php8.4-sqlite3 php8.4-intl php8.4-mbstring \
  php8.4-xml php8.4-zip php8.4-bcmath php8.4-curl
```

Instala Composer:

```bash
php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

### 7. Traer el código
El código está en GitHub como **`anuskaCRM`**. Asegúrate de que el repo es **privado**
(GitHub → repo → *Settings* → *Danger Zone* → *Change visibility → Private*): aunque no hay secretos
en el repo, es interno.

Para clonar un repo privado desde el servidor necesitas autenticarte. Lo más simple: un **token de
acceso** de GitHub (*Settings → Developer settings → Personal access tokens → Fine-grained*, solo lectura
sobre este repo) y usarlo en la URL:

```bash
sudo mkdir -p /var/www && sudo chown ubuntu:ubuntu /var/www
cd /var/www
git clone https://TU_USUARIO:TU_TOKEN@github.com/TU_USUARIO/anuskaCRM.git anuska
cd anuska
composer install --no-dev --optimize-autoloader
```

> Alternativa sin token: `scp -r` la carpeta del proyecto desde tu PC (sin `vendor/` ni `var/`), o una
> *deploy key* SSH de solo lectura. El token es lo más rápido para empezar.

### 8. Configuración de producción (`.env.local`, NO se versiona)
```bash
cat > /var/www/anuska/.env.local <<'EOF'
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=PEGA_AQUI_EL_SECRETO
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
EOF
```

Genera el `APP_SECRET` nuevo y pégalo en el archivo:

```bash
php -r "echo bin2hex(random_bytes(32)).\"\n\";"
```

### 9. Base de datos, assets y primer usuario
La BD es un archivo SQLite (`var/data.db`) que se crea solo al aplicar las migraciones.

```bash
cd /var/www/anuska
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console asset-map:compile
php bin/console cache:clear --env=prod
mkdir -p storage
php bin/console app:user:create --email="ana@tienda.com" --name="Ana" --admin
```

(Los demás usuarios se crean luego desde **Configuración → Usuarios** dentro del CRM.)

### 10. Permisos
`www-data` debe poder escribir en `var/` (donde vive `data.db`) y en `storage/`:

```bash
sudo chown -R www-data:www-data /var/www/anuska/storage /var/www/anuska/var
```

---

## PARTE D — Servir el CRM SOLO por Tailscale

Aquí está el punto clave de seguridad: Nginx escucha **solo en 127.0.0.1** (local), y Tailscale lo
publica en tu red privada con HTTPS. Internet nunca lo ve.

### 11. Nginx en local (127.0.0.1:8080)
```bash
sudo tee /etc/nginx/sites-available/anuska >/dev/null <<'EOF'
server {
    listen 127.0.0.1:8080;
    server_name _;
    root /var/www/anuska/public;

    location / { try_files $uri /index.php$is_args$args; }
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }
    location ~ \.php$ { return 404; }
}
EOF
sudo ln -sf /etc/nginx/sites-available/anuska /etc/nginx/sites-enabled/anuska
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

### 12. Publicarlo en la red privada con HTTPS
```bash
sudo tailscale serve --bg 8080
tailscale serve status
```

`serve status` te dará la URL privada, algo como:

```
https://crm-anuska.tu-tailnet.ts.net/  →  http://127.0.0.1:8080
```

Esa `https://…ts.net` es la dirección que usarán tus padres. Como llega por HTTPS, la app activa sola la
cookie `Secure` y HSTS.

### 13. Cerrar todo lo público (importante)
Como entras por Tailscale (`--ssh`), ya no necesitas puertos públicos:
- En Oracle: **Networking → tu VCN → Security List** → deja la ingress **sin** reglas de entrada
  públicas (o solo SSH desde tu IP mientras terminas; luego bórrala).
- Cortafuegos del sistema (opcional, refuerzo): permite solo la interfaz `tailscale0`.

Comprueba desde fuera de Tailscale (p. ej. datos del móvil sin la app activa) que la IP pública **no**
responde en ningún puerto. Debe ser inaccesible.

---

## PARTE E — Tus padres (lo único que hacen ellos)

1. Instalar la app **Tailscale** (Play Store / App Store / Windows).
2. Iniciar sesión **una vez** con la misma cuenta/organización (les invitas desde el panel de Tailscale,
   menú **Users → Invite**). Queda conectada en segundo plano; no hay que "conectar la VPN" cada vez.
3. Abrir en el navegador la dirección `https://crm-anuska.tu-tailnet.ts.net` y guardarla en favoritos /
   pantalla de inicio.
4. Entrar con su usuario y contraseña del CRM.

Eso es todo. Desde casa, la tienda o el móvil con datos, siempre funciona.

---

## PARTE F — Copias de seguridad (sencillo, para 3 personas)

Respaldar **juntos** el archivo de base de datos y la carpeta de documentos, fuera de `public/` y de
Git. Con SQLite el backup es copiar un fichero (usa `.backup` de sqlite3 para una copia consistente
aunque la app esté en uso):

```bash
sudo mkdir -p /var/backups/anuska && sudo chown ubuntu:ubuntu /var/backups/anuska
cat > /home/ubuntu/backup-anuska.sh <<'EOF'
#!/bin/bash
set -e
D=$(date +%F)
sqlite3 /var/www/anuska/var/data.db ".backup '/var/backups/anuska/data-$D.db'"
tar czf /var/backups/anuska/storage-$D.tar.gz -C /var/www/anuska storage
# Conservar solo los últimos 14 días
find /var/backups/anuska -type f -mtime +14 -delete
EOF
chmod 700 /home/ubuntu/backup-anuska.sh
```

Programarlo cada noche a las 03:00:

```bash
( crontab -l 2>/dev/null; echo "0 3 * * * /home/ubuntu/backup-anuska.sh" ) | crontab -
```

> Recomendado: de vez en cuando copia `/var/backups/anuska` a otro sitio que tú controles (un disco, o
> tu propia nube). Así sobrevives incluso a la pérdida de la VM.

---

## PARTE G — Actualizaciones del CRM

Cuando cambies el código:

```bash
cd /var/www/anuska
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console asset-map:compile
php bin/console cache:clear --env=prod
sudo systemctl reload php8.4-fpm nginx
```

---

## Nota sobre la base de datos

El CRM usa **SQLite** (`var/data.db`): un único archivo, sin servicio de base de datos que instalar ni
mantener. Para 3 usuarios rinde de sobra y el backup es copiar un fichero. Si algún día fuerais muchos
más usuarios escribiendo a la vez, PostgreSQL sería preferible, pero no es este caso.
