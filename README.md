1. Asegúrate de tener PHP y Composer instalados en tu máquina.
2. Abre una terminal y navega hasta el directorio de tu proyecto.
3. Ejecuta `composer install` para instalar las dependencias.
4. Copia el archivo `.env.example` a `.env` y configura tu base de datos local.
5. Ejecuta `php artisan key:generate` para generar una clave de aplicación.
6. Ejecuta `php artisan migrate` para crear las tablas en la base de datos.
7. Finalmente, ejecuta `php artisan serve` para iniciar el servidor de desarrollo.
