<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea un usuario administrador de prueba para el login del panel CoreUI.
     * type_user = 1 -> requerido por AuthController@login (guard 'api', attempt con type_user=1)
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin',
                'surname' => 'Principal',
                'phone' => '0000000000',
                'type_user' => 1,
                'uniqd' => uniqid(),
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
