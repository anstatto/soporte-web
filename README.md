# RM Consuegra — Soporte interno (Inertia + Vue 3)

Sistema de **mensajería interna / tickets** para RM Consuegra SRL.

## Stack

- Laravel 11 + Inertia.js + Vue 3 + Tailwind
- Spatie Permission (`admin`, `soporte`, `solicitante`)
- PostgreSQL

## Roles

| Rol | Uso |
|-----|-----|
| `admin` | Todo + crear usuarios + catálogos |
| `soporte` | Inbox global, Kanban, responder hilos |
| `solicitante` | Abrir solicitudes y conversar solo en las propias |

El registro público está deshabilitado. **Admin crea usuarios** desde `/users/create`.

## Setup

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

Tras cambios de frontend en producción (NSSM): **siempre** `npm run build`.

## Paleta corporativa

Navy `#0B1F3A`, primary `#1E4E79`, accent `#2F6FAD`, surface `#F3F6F9`.

## Notas NSSM

```bash
nssm install LaravelServer "C:\PHP\php.exe" "C:\ruta-proyecto\artisan" "serve" "--host=0.0.0.0" "--port=0000"
nssm start LaravelServer
```

Logs: `storage/logs/laravel.log`
