#!/usr/bin/env bash
# Despliegue por SSH a un servidor Linux.
#
# Uso local (desde Windows Git Bash / WSL / Linux):
#   export DEPLOY_HOST=usuario@ip-o-hostname
#   export DEPLOY_PATH=/var/www/soporte-web
#   ./scripts/deploy-ssh.sh
#
# Opciones:
#   SKIP_BUILD=1     - no correr npm run build en el servidor
#   SKIP_MIGRATE=1   - no correr migraciones
#   BRANCH=main      - rama a desplegar (default: main)

set -euo pipefail

DEPLOY_HOST="${DEPLOY_HOST:-}"
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/soporte-web}"
BRANCH="${BRANCH:-main}"
SKIP_BUILD="${SKIP_BUILD:-0}"
SKIP_MIGRATE="${SKIP_MIGRATE:-0}"

if [[ -z "$DEPLOY_HOST" ]]; then
  echo "Error: define DEPLOY_HOST (ej. deploy@192.168.1.50)"
  echo "  export DEPLOY_HOST=usuario@servidor"
  exit 1
fi

echo "==> Desplegando en $DEPLOY_HOST:$DEPLOY_PATH (rama $BRANCH)"

ssh -o StrictHostKeyChecking=accept-new "$DEPLOY_HOST" bash -s <<REMOTE
set -euo pipefail
cd "$DEPLOY_PATH"

echo "==> Git pull"
git fetch --all --prune
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "==> Composer"
composer install --no-dev --optimize-autoloader --no-interaction

if [[ "$SKIP_BUILD" != "1" ]]; then
  echo "==> Frontend build"
  # Vite y plugins viven en devDependencies: no usar --omit=dev
  npm ci
  npm run build
fi

if [[ "$SKIP_MIGRATE" != "1" ]]; then
  echo "==> Migraciones"
  php artisan migrate --force
fi

echo "==> Caches Laravel"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

echo "==> Permisos storage/bootstrap"
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache

echo "==> Reinicio servicios"
sudo systemctl restart php8.2-fpm || sudo systemctl restart php-fpm || true
sudo systemctl restart soporte-queue || true
sudo systemctl restart soporte-reverb || true
sudo systemctl reload nginx || true

echo "==> Listo"
php artisan --version
REMOTE

echo "==> Despliegue terminado"
