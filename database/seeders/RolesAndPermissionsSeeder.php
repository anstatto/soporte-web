<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Crear permisos
        $permissions = [
            'create tickets',
            'edit tickets',
            'delete tickets',
            'view tickets',
            'assign tickets',
            'comment on tickets',
            'edit estado',
            'create estado',
            'view estado',
            'delete estado',
            'edit departamento',
            'create departamento',
            'view departamento',
            'delete departamento',
            'dashboard resumen',
            'dashboard estadistica',
            'dashboard actividad',
            'dashboard users',
            'view reports',
            'generate reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        //borrar permisos que no existen
        Permission::whereNotIn('name', $permissions)->delete();

        // Crear roles y asignar permisos
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $userRole = Role::firstOrCreate(['name' => 'user']);
        $userRole->givePermissionTo([
            'create tickets',
            'edit tickets',
            'view tickets',
            'create estado',
            'edit estado',
            'view estado',
            'create departamento',
            'edit departamento',
            'view departamento',
            'dashboard actividad',
            'view reports',
            'generate reports',
        ]);
    }
}
