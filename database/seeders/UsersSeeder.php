<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\Role;
use Illuminate\Database\Seeder;

/**
 * Usuarios de desarrollo (entorno Sail).
 * Adaptado a la arquitectura actual: roles vía App\Enums\Role, sin columna password (acceso por magic link/OTP).
 */
class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'José Admin',
                'email' => 'admin@pesquerapp.com',
                'role' => Role::Administrador->value,
            ],
            [
                'name' => 'Carlos Manager',
                'email' => 'manager@pesquerapp.com',
                'role' => Role::Tecnico->value,
            ],
            [
                'name' => 'Ana Operadora',
                'email' => 'operator@pesquerapp.com',
                'role' => Role::Operario->value,
            ],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'role' => $data['role'],
                    'active' => true,
                ]
            );
        }

        // 5 usuarios adicionales de prueba (rol operario)
        for ($i = 1; $i <= 5; $i++) {
            User::firstOrCreate(
                ['email' => "operator{$i}@pesquerapp.com"],
                [
                    'name' => "Operador Prueba {$i}",
                    'role' => Role::Operario->value,
                    'active' => true,
                ]
            );
        }
    }
}
