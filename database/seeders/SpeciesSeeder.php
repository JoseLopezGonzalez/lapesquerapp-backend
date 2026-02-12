<?php

namespace Database\Seeders;

use App\Models\Species;
use App\Models\FishingGear;
use Illuminate\Database\Seeder;

/**
 * Especies (menú Productos). Depende de FishingGearSeeder. Datos de desarrollo.
 */
class SpeciesSeeder extends Seeder
{
    public function run(): void
    {
        $arrastre = FishingGear::where('name', 'Arrastre')->first();
        $nasas = FishingGear::where('name', 'Nasas')->first();
        $gearId = $arrastre?->id ?? $nasas?->id ?? FishingGear::first()?->id;

        if (!$gearId) {
            $this->command->warn('SpeciesSeeder: No hay artes de pesca. Ejecuta antes FishingGearSeeder.');
            return;
        }

        $species = [
            ['name' => 'Pulpo', 'scientific_name' => 'Octopus vulgaris', 'fao' => 'OCT', 'fishing_gear_id' => $gearId],
            ['name' => 'Calamar', 'scientific_name' => 'Loligo vulgaris', 'fao' => 'SQU', 'fishing_gear_id' => $gearId],
            ['name' => 'Sepia', 'scientific_name' => 'Sepia officinalis', 'fao' => 'SQU', 'fishing_gear_id' => $gearId],
            ['name' => 'Merluza', 'scientific_name' => 'Merluccius merluccius', 'fao' => 'HKE', 'fishing_gear_id' => $gearId],
        ];

        foreach ($species as $data) {
            Species::firstOrCreate(
                ['name' => $data['name']],
                [
                    'scientific_name' => $data['scientific_name'],
                    'fao' => $data['fao'],
                    'image' => null,
                    'fishing_gear_id' => $data['fishing_gear_id'],
                ]
            );
        }
    }
}
