<?php

namespace Database\Seeders;

use App\Models\CaptureZone;
use Illuminate\Database\Seeder;

/**
 * Zonas de captura (menú Productos). Datos de desarrollo.
 * Depende de: ninguno.
 */
class CaptureZonesSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            'FAO 27 (Atlántico NE)',
            'FAO 34 (Atlántico Centro-E)',
            'FAO 37 (Mediterráneo)',
        ];

        foreach ($zones as $name) {
            CaptureZone::firstOrCreate(['name' => $name]);
        }
    }
}
