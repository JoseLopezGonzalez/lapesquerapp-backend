<?php

namespace Database\Seeders;

use App\Models\Caliber;
use Illuminate\Database\Seeder;

/**
 * Calibres por especie (guía entorno desarrollo PesquerApp).
 */
class CalibersSeeder extends Seeder
{
    public function run(): void
    {
        $calibers = [
            // Pulpo
            ['name' => 'T1 (>2kg)', 'min_weight' => 2000, 'max_weight' => null, 'species' => 'octopus'],
            ['name' => 'T2 (1-2kg)', 'min_weight' => 1000, 'max_weight' => 2000, 'species' => 'octopus'],
            ['name' => 'T3 (500g-1kg)', 'min_weight' => 500, 'max_weight' => 1000, 'species' => 'octopus'],
            ['name' => 'T4 (<500g)', 'min_weight' => null, 'max_weight' => 500, 'species' => 'octopus'],
            // Calamar
            ['name' => 'U5 (<150g)', 'min_weight' => null, 'max_weight' => 150, 'species' => 'squid'],
            ['name' => 'U8 (150-200g)', 'min_weight' => 150, 'max_weight' => 200, 'species' => 'squid'],
            ['name' => 'U10 (200-300g)', 'min_weight' => 200, 'max_weight' => 300, 'species' => 'squid'],
            ['name' => 'U15 (>300g)', 'min_weight' => 300, 'max_weight' => null, 'species' => 'squid'],
            // Sepia
            ['name' => '1-2 (>500g)', 'min_weight' => 500, 'max_weight' => null, 'species' => 'cuttlefish'],
            ['name' => '2-4 (200-500g)', 'min_weight' => 200, 'max_weight' => 500, 'species' => 'cuttlefish'],
            ['name' => '4-6 (100-200g)', 'min_weight' => 100, 'max_weight' => 200, 'species' => 'cuttlefish'],
            ['name' => '6+ (<100g)', 'min_weight' => null, 'max_weight' => 100, 'species' => 'cuttlefish'],
        ];

        foreach ($calibers as $caliber) {
            Caliber::firstOrCreate(
                [
                    'name' => $caliber['name'],
                    'species' => $caliber['species'],
                ],
                $caliber
            );
        }
    }
}
