# RM Consuegra — Soporte interno (Inertia + Vue 3)

Sistema de **mensajería interna / tickets** para RM Consuegra SRL.

## Stack

- Laravel 11 + Inertia.js + Vue 3 + Tailwind + Element Plus
- Spatie Permission (`admin`, `soporte`, `solicitante`)
- PostgreSQL
- Laravel Reverb (tiempo real)
- DomPDF + PhpSpreadsheet (reportes)

## Roles

| Rol | Uso |
|-----|-----|
| `admin` | Todo + crear usuarios + catálogos |
| `soporte` | Inbox global, Kanban, responder hilos, reportes |
| `solicitante` | Abrir solicitudes y conversar solo en las propias |

El registro público está deshabilitado. **Admin crea usuarios** desde `/users/create`.

## Setup local

```bash
composer install --ignore-platform-reqs
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
# Crea roles, catálogos y super admin (ver SUPER_ADMIN_* en .env)
npm install
npm run build
# desarrollo:
composer run dev
# o:
php artisan serve
npm run dev
```

Tras cambios de frontend en producción: **siempre** `npm run build`.

## Reportes

En `/reportes` puedes:

- Filtrar por fechas, usuario y estado
- **Vista previa PDF** en un visor embebido
- Descargar PDF / Excel / CSV (básico, detallado, estadístico, rendimiento)

El rendimiento calcula horas de resolución sobre tickets en estado *Cerrado* (o equivalentes).

## Configuración interna

Menú **Configuración** (admin):

- Usuarios, áreas, departamentos, estados, etiquetas, roles, **ajustes del sistema**, perfil

En **Ajustes** puedes cambiar nombre de la app, pie de reportes PDF, remitente de correo y probar el mailer. SMTP host/credenciales siguen en `.env`.

## Despliegue Linux (SSH)

Documentación completa: [`deploy/README.md`](deploy/README.md)

Resumen:

```bash
# En el servidor (primera vez)
sudo bash scripts/install-linux.sh
# Configurar Nginx: deploy/nginx/soporte-web.conf

# Desde tu máquina (actualizaciones)
export DEPLOY_HOST=usuario@IP
export DEPLOY_PATH=/var/www/soporte-web
bash scripts/deploy-ssh.sh
```

Servicios systemd incluidos: cola, Reverb y scheduler.

## Notas NSSM (Windows legacy)

```bash
nssm install LaravelServer "C:\PHP\php.exe" "C:\ruta-proyecto\artisan" "serve" "--host=0.0.0.0" "--port=0000"
nssm start LaravelServer
```

Para producción enterprise se recomienda **Linux + Nginx** (ver `deploy/`).

Logs: `storage/logs/laravel.log`
