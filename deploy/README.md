# Despliegue en Linux (producción)

Guía rápida para instalar **soporte-web** en un servidor Linux con Nginx + PHP-FPM + PostgreSQL, y actualizarlo por SSH.

## Requisitos del servidor

- Ubuntu 22.04 / 24.04 (o similar)
- PHP 8.2+ con extensiones: `pgsql`, `mbstring`, `xml`, `gd`, `zip`, `bcmath`, `curl`, `intl`
- Composer 2
- Node.js 20+ (solo para `npm run build`)
- PostgreSQL 14+
- Nginx
- Acceso SSH

```bash
sudo apt update
sudo apt install -y nginx postgresql postgresql-contrib \
  php8.2-fpm php8.2-cli php8.2-pgsql php8.2-mbstring php8.2-xml \
  php8.2-gd php8.2-zip php8.2-bcmath php8.2-curl php8.2-intl \
  unzip git curl
```

## 1. Clonar y primera instalación

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone <URL_DEL_REPO> soporte-web
cd soporte-web
sudo bash scripts/install-linux.sh
```

Edita `.env` (DB, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, Reverb, super admin) y vuelve a cachear:

```bash
php artisan config:cache
```

## 2. Nginx

```bash
sudo cp deploy/nginx/soporte-web.conf /etc/nginx/sites-available/soporte-web
# Edita server_name, root y socket PHP-FPM si hace falta
sudo nano /etc/nginx/sites-available/soporte-web
sudo ln -sf /etc/nginx/sites-available/soporte-web /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## 3. Servicios (systemd)

El script de instalación ya habilita:

| Servicio | Rol |
|----------|-----|
| `soporte-queue` | Worker de colas (`queue:work`) |
| `soporte-reverb` | WebSockets (chat / notificaciones) |
| `soporte-scheduler.timer` | Cron Laravel cada minuto |

```bash
sudo systemctl status soporte-queue soporte-reverb soporte-scheduler.timer
```

## 4. Variables Reverb en producción

En `.env` del servidor (ejemplo detrás de Nginx en el mismo host):

```env
BROADCAST_CONNECTION=reverb
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...

# Vite (valores literales; deben coincidir con lo que ve el navegador)
VITE_REVERB_APP_KEY=...
VITE_REVERB_HOST=soporte.tudominio.local
VITE_REVERB_PORT=80
VITE_REVERB_SCHEME=http
```

Tras cambiar `VITE_*`, hay que **rebuild** (`npm run build`).

## 4a. LiveKit Cloud (llamadas — opción más barata)

Plan gratis de https://cloud.livekit.io. Solo media; el ringing usa Reverb.

```env
LIVEKIT_ENABLED=true
LIVEKIT_URL=wss://tu-proyecto.livekit.cloud
LIVEKIT_API_KEY=...
LIVEKIT_API_SECRET=...
```

Luego `php artisan config:cache`. No hace falta rebuild de Vite (las keys van solo al backend).

## 4b. HTTPS (opcional pero recomendado)

Usa `deploy/nginx/soporte-web-ssl.conf` tras emitir certificado (ej. Certbot):

```bash
sudo certbot certonly --nginx -d soporte.tudominio.local
sudo cp deploy/nginx/soporte-web-ssl.conf /etc/nginx/sites-available/soporte-web
# Ajusta server_name y rutas del certificado
sudo nginx -t && sudo systemctl reload nginx
```

En `.env`:

```env
APP_URL=https://soporte.tudominio.local
SESSION_SECURE_COOKIE=true
VITE_REVERB_HOST=soporte.tudominio.local
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Luego `npm run build` y `php artisan config:cache`.

## 5. Despliegues siguientes (desde tu PC por SSH)

```bash
# Git Bash / WSL / Linux
export DEPLOY_HOST=usuario@IP_O_HOSTNAME
export DEPLOY_PATH=/var/www/soporte-web
export BRANCH=main
bash scripts/deploy-ssh.sh
```

El script hace: `git pull` → `composer install` → `npm ci && npm run build` → `migrate --force` → cachés → reinicio de servicios.

## 6. Permisos SSH recomendados

En el servidor, el usuario de deploy debería poder:

- Escribir en `/var/www/soporte-web`
- Ejecutar sin contraseña (sudoers) reinicios de `php*-fpm`, `nginx`, `soporte-queue`, `soporte-reverb`

Ejemplo `/etc/sudoers.d/soporte-deploy`:

```
deploy ALL=(ALL) NOPASSWD: /bin/systemctl restart php8.2-fpm, /bin/systemctl reload nginx, /bin/systemctl restart soporte-queue, /bin/systemctl restart soporte-reverb, /bin/chown, /bin/chmod
```

## Checklist post-deploy

- [ ] Login admin funciona
- [ ] Tablero Kanban carga
- [ ] Reportes → vista previa PDF
- [ ] Configuración → Ajustes del sistema
- [ ] Chat / notificaciones en tiempo real (Reverb)
- [ ] Colas: `php artisan queue:failed` vacío
- [ ] Logs: `storage/logs/laravel.log` y `/var/log/nginx/soporte-web.*.log`
