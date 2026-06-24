<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

/**
 * Usuario operario de tienda para pruebas (store operator).
 * Depende de: ninguno (crea usuario si no existe).
 */
class StoreOperatorUserSeeder extends Seeder
{
    public function run(): void
    {
        // assigned_store_id => null cuando no hay tiendas en el tenant (ej. deploy desarrollo)
        $user = User::firstOrCreate(
            ['email' => 'store.operator@pesquerapp.com'],
            [
                'name' => 'Operario Tienda (demo)',
                'role' => 'operario',
                'assigned_store_id' => null,
            ]
        );

        if ($user->wasRecentlyCreated === false && $user->role !== 'operario') {
            $user->update(['role' => 'operario']);
        }

        $this->command?->info("Usuario operario creado: {$user->email}");
    }
}
