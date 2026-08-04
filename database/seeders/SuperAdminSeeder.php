<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('SUPER_ADMIN_USERNAME', 'superadmin');
        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@rmconsuegra.local');
        $password = env('SUPER_ADMIN_PASSWORD', 'SuperAdmin123!');
        $name = env('SUPER_ADMIN_NAME', 'Super Admin');

        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->syncRoles(['admin']);

        $this->command?->info("Super admin listo → usuario: {$username} | clave: {$password}");
    }
}
