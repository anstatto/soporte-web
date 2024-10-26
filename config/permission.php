<?php

return [

    'models' => [

        /*
         * Cuando se usa el trait "HasPermissions" de este paquete, necesitamos saber qué
         * modelo Eloquent se debe usar para recuperar tus permisos. Por supuesto,
         * a menudo es solo el modelo "Permission", pero puedes usar el que prefieras.
         *
         * El modelo que deseas usar como modelo de Permiso debe implementar el
         * contrato `Spatie\Permission\Contracts\Permission`.
         */

        'permission' => Spatie\Permission\Models\Permission::class,

        /*
         * Cuando se usa el trait "HasRoles" de este paquete, necesitamos saber qué
         * modelo Eloquent se debe usar para recuperar tus roles. Por supuesto,
         * a menudo es solo el modelo "Role", pero puedes usar el que prefieras.
         *
         * El modelo que deseas usar como modelo de Rol debe implementar el
         * contrato `Spatie\Permission\Contracts\Role`.
         */

        'role' => Spatie\Permission\Models\Role::class,

    ],

    'table_names' => [

        /*
         * Cuando se usa el trait "HasRoles" de este paquete, necesitamos saber qué
         * tabla se debe usar para recuperar tus roles. Hemos elegido un valor
         * predeterminado básico, pero puedes cambiarlo fácilmente a cualquier tabla que prefieras.
         */

        'roles' => 'roles',

        /*
         * Cuando se usa el trait "HasPermissions" de este paquete, necesitamos saber qué
         * tabla se debe usar para recuperar tus permisos. Hemos elegido un valor
         * predeterminado básico, pero puedes cambiarlo fácilmente a cualquier tabla que prefieras.
         */

        'permissions' => 'permissions',

        /*
         * Cuando se usa el trait "HasPermissions" de este paquete, necesitamos saber qué
         * tabla se debe usar para recuperar los permisos de tus modelos. Hemos elegido un
         * valor predeterminado básico, pero puedes cambiarlo fácilmente a cualquier tabla que prefieras.
         */

        'model_has_permissions' => 'model_has_permissions',

        /*
         * Cuando se usa el trait "HasRoles" de este paquete, necesitamos saber qué
         * tabla se debe usar para recuperar los roles de tus modelos. Hemos elegido un
         * valor predeterminado básico, pero puedes cambiarlo fácilmente a cualquier tabla que prefieras.
         */

        'model_has_roles' => 'model_has_roles',

        /*
         * Cuando se usa el trait "HasRoles" de este paquete, necesitamos saber qué
         * tabla se debe usar para recuperar los permisos de tus roles. Hemos elegido un
         * valor predeterminado básico, pero puedes cambiarlo fácilmente a cualquier tabla que prefieras.
         */

        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        /*
         * Cambia esto si deseas nombrar los pivotes relacionados de otra manera que no sea la predeterminada
         */
        'role_pivot_key' => null, // predeterminado 'role_id',
        'permission_pivot_key' => null, // predeterminado 'permission_id',

        /*
         * Cambia esto si deseas nombrar la clave primaria del modelo relacionado de otra manera que no sea
         * `model_id`.
         *
         * Por ejemplo, esto sería útil si tus claves primarias son todas UUIDs. En
         * ese caso, nombra esto `model_uuid`.
         */

        'model_morph_key' => 'model_id',

        /*
         * Cambia esto si deseas usar la función de equipos y la clave foránea del modelo relacionado
         * es otra que no sea `team_id`.
         */

        'team_foreign_key' => 'team_id',
    ],

    /*
     * Cuando se establece en true, el método para verificar permisos se registrará en el gate.
     * Establece esto en false si deseas implementar lógica personalizada para verificar permisos.
     */

    'register_permission_check_method' => true,

    /*
     * Cuando se establece en true, se registrará el listener del evento Laravel\Octane\Events\OperationTerminated
     * esto actualizará los permisos en cada TickTerminated, TaskTerminated y RequestTerminated
     * NOTA: Esto no debería ser necesario en la mayoría de los casos, pero una combinación de Octane/Vapor se benefició de ello.
     */
    'register_octane_reset_listener' => false,

    /*
     * Función de Equipos.
     * Cuando se establece en true, el paquete implementa equipos usando el 'team_foreign_key'.
     * Si deseas que las migraciones registren el 'team_foreign_key', debes
     * establecer esto en true antes de hacer la migración.
     * Si ya hiciste la migración, entonces debes hacer una nueva migración para también
     * agregar 'team_foreign_key' a 'roles', 'model_has_roles' y 'model_has_permissions'
     * (ver el archivo de migración de la última versión de este paquete)
     */

    'teams' => false,

    /*
     * Credenciales del Cliente de Passport
     * Cuando se establece en true, el paquete usará el Cliente de Passport para verificar permisos
     */

    'use_passport_client_credentials' => false,

    /*
     * Cuando se establece en true, los nombres de los permisos requeridos se agregan a los mensajes de excepción.
     * Esto podría considerarse una fuga de información en algunos contextos, por lo que la configuración predeterminada
     * es false aquí para una seguridad óptima.
     */

    'display_permission_in_exception' => false,

    /*
     * Cuando se establece en true, los nombres de los roles requeridos se agregan a los mensajes de excepción.
     * Esto podría considerarse una fuga de información en algunos contextos, por lo que la configuración predeterminada
     * es false aquí para una seguridad óptima.
     */

    'display_role_in_exception' => false,

    /*
     * Por defecto, las búsquedas de permisos con comodines están deshabilitadas.
     * Consulta la documentación para entender la sintaxis soportada.
     */

    'enable_wildcard_permission' => false,

    /*
     * La clase a usar para interpretar permisos con comodines.
     * Si necesitas modificar los delimitadores, sobrescribe la clase y especifica su nombre aquí.
     */
    // 'permission.wildcard_permission' => Spatie\Permission\WildcardPermission::class,

    /* Configuraciones específicas de caché */

    'cache' => [

        /*
         * Por defecto, todos los permisos se almacenan en caché durante 24 horas para acelerar el rendimiento.
         * Cuando los permisos o roles se actualizan, la caché se vacía automáticamente.
         */

        'expiration_time' => \DateInterval::createFromDateString('24 hours'),

        /*
         * La clave de caché utilizada para almacenar todos los permisos.
         */

        'key' => 'spatie.permission.cache',

        /*
         * Opcionalmente, puedes indicar un controlador de caché específico para usar para el almacenamiento en caché de permisos y
         * roles usando cualquiera de los controladores `store` listados en el archivo de configuración cache.php.
         * Usar 'default' aquí significa usar el `default` establecido en cache.php.
         */

        'store' => 'default',
    ],
];
