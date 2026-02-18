<?php

namespace Database\Seeders;

use App\Models\FAOZone;
use Illuminate\Database\Seeder;

/**
 * Zonas FAO (guía entorno desarrollo PesquerApp).
 * Depende de: ninguno.
 */
class FAOZonesSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['code' => '27', 'name' => 'Atlántico Nordeste', 'description' => 'FAO 27 - Zona pesquera del Atlántico Nordeste'],
            ['code' => '34', 'name' => 'Atlántico Centro-Este', 'description' => 'FAO 34 - Zona pesquera del Atlántico Centro-Este'],
            ['code' => '37', 'name' => 'Mediterráneo', 'description' => 'FAO 37 - Mar Mediterráneo y Mar Negro'],
            ['code' => '41', 'name' => 'Atlántico Sudoeste', 'description' => 'FAO 41 - Zona pesquera del Atlántico Sudoeste'],
            ['code' => '47', 'name' => 'Atlántico Sudeste', 'description' => 'FAO 47 - Zona pesquera del Atlántico Sudeste'],
            ['code' => '51', 'name' => 'Océano Índico Occidental', 'description' => 'FAO 51 - Zona pesquera del Índico Occidental'],
            ['code' => '87', 'name' => 'Pacífico Sudeste', 'description' => 'FAO 87 - Zona pesquera del Pacífico Sudeste'],
        ];

        foreach ($zones as $zone) {
            FAOZone::firstOrCreate(
                ['code' => $zone['code']],
                $zone
            );
        }

        // Subzonas para FAO 27 (primer registro creado)
        $fao27 = FAOZone::where('code', '27')->first();
        if ($fao27) {
            $subzones27 = [
                ['code' => '27.8.c', 'name' => 'Cantábrico', 'parent_id' => $fao27->id],
                ['code' => '27.9.a', 'name' => 'Golfo de Cádiz', 'parent_id' => $fao27->id],
            ];
            foreach ($subzones27 as $subzone) {
                FAOZone::firstOrCreate(
                    ['code' => $subzone['code']],
                    $subzone
                );
            }
        }
    }
}
