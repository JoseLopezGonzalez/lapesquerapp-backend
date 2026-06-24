<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

/**
 * Usuario específico tenant Algar Seafood (demo).
 * Depende de: ninguno.
 */
class AlgarSeafoodUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'app@algarseafood.pt'],
            [
                'name' => 'Algar Seafood (Operario)',
                'role' => 'operario',
                'assigned_store_id' => 1,
            ]
        );

        if ($user->wasRecentlyCreated === false && $user->role !== 'operario') {
            $user->update(['role' => 'operario']);
        }

        $this->command?->info("Usuario Algar Seafood creado: {$user->email}");
    }
}
