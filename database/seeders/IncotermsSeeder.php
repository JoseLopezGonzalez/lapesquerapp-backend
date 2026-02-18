<?php

namespace Database\Seeders;

use App\Models\Incoterm;
use Illuminate\Database\Seeder;

/**
 * Incoterms (menú). Datos de desarrollo.
 * Depende de: ninguno.
 */
class IncotermsSeeder extends Seeder
{
    public function run(): void
    {
        $incoterms = [
            ['code' => 'EXW', 'description' => 'Ex Works'],
            ['code' => 'FCA', 'description' => 'Free Carrier'],
            ['code' => 'CPT', 'description' => 'Carriage Paid To'],
            ['code' => 'CIP', 'description' => 'Carriage and Insurance Paid To'],
            ['code' => 'DAP', 'description' => 'Delivered at Place'],
            ['code' => 'DPU', 'description' => 'Delivered at Place Unloaded'],
            ['code' => 'DDP', 'description' => 'Delivered Duty Paid'],
        ];

        foreach ($incoterms as $data) {
            Incoterm::firstOrCreate(
                ['code' => $data['code']],
                ['description' => $data['description']]
            );
        }
    }
}
