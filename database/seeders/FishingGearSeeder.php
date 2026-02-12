<?php

namespace Database\Seeders;

use App\Models\FishingGear;
use Illuminate\Database\Seeder;

/**
 * Artes de pesca (menú Productos). Datos de desarrollo.
 */
class FishingGearSeeder extends Seeder
{
    public function run(): void
    {
        $gears = [
            'Arrastre',
            'Cerco',
            'Palangre',
            'Nasas',
            'Almadraba',
        ];

        foreach ($gears as $name) {
            FishingGear::firstOrCreate(['name' => $name]);
        }
    }
}
