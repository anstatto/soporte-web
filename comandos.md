# Comandos de desarrollo

Guía rápida para trabajar en local con **soporte-web**.

---

## Primera vez (setup)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
```

Edita `.env` (PostgreSQL, `SUPER_ADMIN_*`, Reverb) antes de arrancar.

---

## Arrancar en desarrollo

### Opción recomendada (todo junto)

```bash
composer run dev
```

Levanta: `artisan serve` + cola + logs (`pail`) + Vite.

### Opción con WebSockets (chat / notificaciones)

En una terminal:

```bash
npm run dev:all
```

Levanta: servidor PHP + Vite + Reverb.

O por separado (3 terminales):

```bash
php artisan serve
npm run dev
php artisan reverb:start
```

Cola (si no usas `composer run dev`):

```bash
php artisan queue:listen --tries=1
```

---

## Frontend

```bash
npm run dev      # Vite hot reload
npm run build    # assets de producción
```

Tras cambiar `VITE_*` en `.env`, hay que volver a correr `npm run build` (o reiniciar `npm run dev`).

---

## Base de datos

```bash
php artisan migrate
php artisan migrate:fresh --seed   # borra todo y reseeds (¡cuidado!)
php artisan db:seed
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=CatalogSeeder
```

---

## Cachés y limpieza (local)

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan optimize:clear
```

En local **no** uses `config:cache` si estás cambiando el `.env` a menudo.

---

## Utilidades útiles

```bash
php artisan route:list
php artisan route:list --path=reportes
php artisan tinker
php artisan pail                 # logs en vivo
./vendor/bin/pint                # estilo PHP
php artisan test
```

---

## Credenciales por defecto (seeder)

Definidas en `.env` como `SUPER_ADMIN_*` (ver `.env.example`).

Tras el seed, entra con ese usuario/admin.

---

## PWA (instalar como app)

En **producción** (`npm run build`) la app registra un service worker y un manifest.

- Chrome/Edge: icono de instalación en la barra de direcciones, o menú → “Instalar aplicación”.
- Requiere HTTPS (o localhost).
- Es una PWA ligera (acceso rápido + icono en escritorio); el chat/notificaciones siguen necesitando red.

---

## LiveKit (llamadas / video) — plan gratis

Opción más barata: **LiveKit Cloud** (créditos gratis al mes). Media va por LiveKit; el “ringing” usa Reverb.

1. Cuenta en https://cloud.livekit.io → crear proyecto
2. **Settings → Keys**: copiar `URL` (`wss://…livekit.cloud`), API Key y Secret
3. En `.env`:

```env
LIVEKIT_ENABLED=true
LIVEKIT_URL=wss://tu-proyecto.livekit.cloud
LIVEKIT_API_KEY=APIxxxxxxxx
LIVEKIT_API_SECRET=secreto
LIVEKIT_RING_TIMEOUT=45
```

4. `php artisan config:clear` y reinicia Vite / Reverb
5. En el menú verás **Llamadas**. Configura keys en **Ajustes** (admin) o en `.env`.
6. Botones de teléfono/cámara también en Bandeja y Tablero (tarjeta → Miembros)

Permisos nuevos: `use calls` (llamar) y `manage calls` (admin / configurar). Se asignan por defecto a admin, soporte y solicitante (`use calls`). Admin tiene ambos.

---

## Despliegue Linux (referencia)

Ver detalle en [`deploy/README.md`](deploy/README.md).

```bash
# Primera vez en el servidor
sudo bash scripts/install-linux.sh

# Actualizar desde tu PC
export DEPLOY_HOST=usuario@IP
export DEPLOY_PATH=/var/www/soporte-web
bash scripts/deploy-ssh.sh
```
