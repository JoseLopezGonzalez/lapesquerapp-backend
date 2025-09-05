<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class StoreOperatorUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuario de prueba con rol store_operator
        $user = User::firstOrCreate(
            ['email' => 'store.operator@test.com'],
            [
                'name' => 'Store Operator Test',
                'password' => Hash::make('password123'),
                'assigned_store_id' => 1, // ID de tienda de prueba
                'company_name' => 'Tienda de Prueba S.A.',
                'company_logo_url' => 'https://via.placeholder.com/150x150/007bff/ffffff?text=LOGO',
            ]
        );

        // Asignar el rol store_operator
        $role = Role::where('name', 'store_operator')->first();
        if ($role && !$user->hasRole('store_operator')) {
            $user->assignRole('store_operator');
        }

        $this->command->info("Usuario store_operator creado: {$user->email}");
    }
}