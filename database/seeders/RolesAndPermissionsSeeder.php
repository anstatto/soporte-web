<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'create tickets',
            'edit tickets',
            'delete tickets',
            'view tickets',
            'assign tickets',
            'comment on tickets',
            'chat with users',
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
            'manage users',
            'view users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Permission::whereNotIn('name', $permissions)->delete();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->is_agent = true;
        $adminRole->save();
        $adminRole->syncPermissions(Permission::all());

        $soporteRole = Role::firstOrCreate(['name' => 'soporte', 'guard_name' => 'web']);
        $soporteRole->is_agent = true;
        $soporteRole->save();
        $soporteRole->syncPermissions([
            'create tickets',
            'edit tickets',
            'delete tickets',
            'view tickets',
            'assign tickets',
            'comment on tickets',
            'chat with users',
            'view estado',
            'view departamento',
            'dashboard resumen',
            'dashboard estadistica',
            'dashboard actividad',
            'view reports',
            'generate reports',
        ]);

        $solicitanteRole = Role::firstOrCreate(['name' => 'solicitante', 'guard_name' => 'web']);
        $solicitanteRole->is_agent = false;
        $solicitanteRole->save();
        $solicitanteRole->syncPermissions([
            'create tickets',
            'view tickets',
            'comment on tickets',
            'chat with users',
            'dashboard actividad',
        ]);

        // Migrar rol legado "user" → "soporte"
        $legacy = Role::where('name', 'user')->first();
        if ($legacy) {
            foreach ($legacy->users as $user) {
                if (! $user->hasRole(['admin', 'soporte', 'solicitante'])) {
                    $user->assignRole('soporte');
                }
                $user->removeRole('user');
            }
            $legacy->delete();
        }
    }
}
