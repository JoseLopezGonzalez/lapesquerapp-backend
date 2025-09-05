<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'superuser',
                'description' => 'Superusuario con acceso completo al sistema'
            ],
            [
                'name' => 'manager',
                'description' => 'Gerente con permisos de administración'
            ],
            [
                'name' => 'admin',
                'description' => 'Administrador con permisos limitados'
            ],
            [
                'name' => 'store_operator',
                'description' => 'Operador de tienda con acceso a funciones específicas de la tienda asignada'
            ]
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}