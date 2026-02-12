<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class StoreOperatorUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // assigned_store_id => null cuando no hay tiendas en el tenant (ej. deploy desarrollo)
        $user = User::firstOrCreate(
            ['email' => 'store.operator@test.com'],
            [
                'name' => 'Store Operator Test',
                'role' => 'operario',
                'assigned_store_id' => null,
                'company_name' => 'Tienda de Prueba S.A.',
                'company_logo_url' => 'https://via.placeholder.com/150x150/007bff/ffffff?text=LOGO',
            ]
        );

        if ($user->wasRecentlyCreated === false && $user->role !== 'operario') {
            $user->update(['role' => 'operario']);
        }

        $this->command->info("Usuario operario creado: {$user->email}");
    }
}
