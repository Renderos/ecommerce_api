<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Usuarios de prueba. Las credenciales se configuran con las variables SEED_* del .env.
     */
    public function run(): void
    {
        $seed = config('services.seed');

        $admin = User::updateOrCreate(
            ['email' => $seed['admin_email']],
            [
                'name' => 'Administrador',
                'password' => $seed['admin_password'],
                'email_verified_at' => now(),
            ]
        );
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        User::updateOrCreate(
            ['email' => $seed['customer_email']],
            [
                'name' => 'Cliente Demo',
                'password' => $seed['customer_password'],
                'phone' => '+56 9 1234 5678',
                'email_verified_at' => now(),
            ]
        );
    }
}
