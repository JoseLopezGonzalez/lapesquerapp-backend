<?php

namespace Database\Seeders;

use App\Models\Salesperson;
use Illuminate\Database\Seeder;

/**
 * Comerciales / Vendedores (menú). Datos de desarrollo.
 */
class SalespeopleSeeder extends Seeder
{
    public function run(): void
    {
        $salespeople = [
            ['name' => 'Comercial Demo', 'emails' => 'comercial@demo.local'],
        ];

        foreach ($salespeople as $data) {
            Salesperson::firstOrCreate(
                ['name' => $data['name']],
                ['emails' => $data['emails'] ?? null]
            );
        }
    }
}
