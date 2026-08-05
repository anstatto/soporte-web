#!/usr/bin/env bash
# Primera instalacion en el servidor Linux (ejecutar UNA vez via SSH).
#
#   ssh usuario@servidor
#   cd /var/www && sudo git clone <repo> soporte-web
#   cd soporte-web && sudo bash scripts/install-linux.sh

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

echo "==> Directorio: $APP_DIR"

if [[ ! -f .env ]]; then
  if [[ -f .env.example ]]; then
    cp .env.example .env
    echo "Creado .env desde .env.example - editarlo antes de continuar (DB, APP_URL, Reverb, SUPER_ADMIN_*)."
  else
    echo "Falta .env / .env.example"
    exit 1
  fi
fi

echo "==> Dependencias PHP"
composer install --no-dev --optimize-autoloader --no-interaction

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force
fi

echo "==> Frontend"
npm ci
npm run build

echo "==> Base de datos"
php artisan migrate --force
php artisan db:seed --force

echo "==> Storage link"
php artisan storage:link || true

echo "==> Caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Permisos"
sudo chown -R www-data:www-data "$APP_DIR"
sudo chmod -R ug+rwx storage bootstrap/cache

echo "==> Systemd units"
sudo cp deploy/systemd/soporte-queue.service /etc/systemd/system/
sudo cp deploy/systemd/soporte-reverb.service /etc/systemd/system/
sudo cp deploy/systemd/soporte-scheduler.service /etc/systemd/system/
sudo cp deploy/systemd/soporte-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now soporte-queue soporte-reverb soporte-scheduler.timer

echo "==> Nginx"
echo "Copia y ajusta: sudo cp deploy/nginx/soporte-web.conf /etc/nginx/sites-available/soporte-web"
echo "Luego: sudo ln -sf /etc/nginx/sites-available/soporte-web /etc/nginx/sites-enabled/"
echo "       sudo nginx -t && sudo systemctl reload nginx"

echo ""
echo "Instalacion base lista. Revisa .env (APP_URL, DB_*, REVERB_*, VITE_REVERB_*)."
