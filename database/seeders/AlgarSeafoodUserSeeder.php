<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AlgarSeafoodUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuario de Algar Seafood con rol store_operator
        $user = User::firstOrCreate(
            ['email' => 'app@algarseafood.pt'],
            [
                'name' => 'Algarseafood',
                'password' => Hash::make('algarSEAFOOD2025'),
                'assigned_store_id' => 1, // ID de tienda asignada
                'company_name' => 'Algar Seafood',
                'company_logo_url' => null, // Se puede agregar después si es necesario
            ]
        );

        // Asignar el rol store_operator
        $role = Role::where('name', 'store_operator')->first();
        if ($role && !$user->hasRole('store_operator')) {
            $user->assignRole('store_operator');
        }

        $this->command->info("Usuario Algar Seafood creado: {$user->email}");
    }
}