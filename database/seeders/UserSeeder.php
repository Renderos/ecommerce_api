<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Usuarios de prueba. Las contraseñas se toman del .env para no dejarlas fijas en el código.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@ecommerce.test')],
            [
                'name' => 'Administrador',
                'password' => env('SEED_ADMIN_PASSWORD', 'Admin123!'),
                'email_verified_at' => now(),
            ]
        );
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        User::updateOrCreate(
            ['email' => env('SEED_CUSTOMER_EMAIL', 'cliente@ecommerce.test')],
            [
                'name' => 'Cliente Demo',
                'password' => env('SEED_CUSTOMER_PASSWORD', 'Cliente123!'),
                'phone' => '+56 9 1234 5678',
                'email_verified_at' => now(),
            ]
        );
    }
}
