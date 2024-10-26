# Instrucciones para Configurar y Ejecutar el Proyecto Laravel

## Requisitos previos

Antes de comenzar, asegúrate de tener instalado:

1. **PHP**: Descárgalo de [php.net](https://www.php.net/downloads.php).
2. **Composer**: Instálalo desde [getcomposer.org](https://getcomposer.org/download/).
3. **Node.js y NPM**: Descarga Node.js de [nodejs.org](https://nodejs.org/).

## Configuración del proyecto

1. Clona el repositorio:
   ```bash
   git clone https://github.com/tu-usuario/tu-repo.git
   cd tu-repo
   ```

2. Instala dependencias PHP:
   ```bash
   composer install
   ```

3. Configura el archivo .env:
   ```bash
   cp .env.example .env
   ```
   Edita .env con los datos de tu base de datos.

4. Genera la clave de la aplicación:
   ```bash
   php artisan key:generate
   ```

5. Ejecuta las migraciones:
   ```bash
   php artisan migrate
   ```

6. Compila los assets (si es necesario):
   ```bash
   npm run build
   ```

## Despliegue con NSSM

1. Instala NSSM:
   ```bash
   choco install nssm
   ```
   win cmd
   ```bash
   curl -L -o nssm.zip https://nssm.cc/release/nssm-2.24.zip
   nssm.exe 
   --coloca la ruta ejecutable en variables c:/nssm/win64/
   ```

2. Configura el servicio:
   ```bash
   nssm install LaravelServer "C:\PHP\php.exe" "C:\ruta-proyecto\artisan" "serve" "--host=0.0.0.0" "--port=0000"
   ```

3. Inicia el servicio:
   ```bash
   nssm start LaravelServer
   ```

Accede a la aplicación en: http://0.0.0.0:0000

## Notas adicionales

- Revisa logs en `storage/logs/laravel.log`
- Para detener: `nssm stop LaravelServer`
- Para eliminar: `nssm remove LaravelServer confirm`
- Para reiniciar: `nssm restart LaravelServer`
- Para ver el estado: `nssm status LaravelServer`
- Para editar la configuración: `nssm edit LaravelServer`
- Para ver los parámetros: `nssm get LaravelServer`

## Créditos

- [Laravel](https://laravel.com)
- [NSSM](https://nssm.cc/)
- [PHP](https://www.php.net/)
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/)
- [Chocolatey](https://chocolatey.org/)
