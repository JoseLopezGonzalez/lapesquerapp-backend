<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Species;
use App\Models\CaptureZone;
use App\Models\ProductFamily;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Productos de desarrollo (nombre, especie, zona captura, familia opcional).
 * Fuente: Análisis esquema tenant. Depende de: Species, CaptureZones, ProductFamily.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $species = Species::all();
        $zones = CaptureZone::all();
        $family = ProductFamily::first();

        if ($species->isEmpty() || $zones->isEmpty()) {
            $this->command->warn('ProductSeeder: Ejecuta antes SpeciesSeeder y CaptureZonesSeeder.');
            return;
        }

        // Nombres realistas (backup Brisamar: "Pulpo Fresco -1kg", "+1kg", etc.)
        $names = [
            'Pulpo Fresco -1kg',
            'Pulpo Fresco +1kg',
            'Merluza congelada eviscerada',
            'Pulpo congelado entero',
            'Calamar congelado anillas',
            'Sepia congelada entera',
            'Merluza fileteada IQF',
            'Pulpo cocido congelado',
            'Calamar entero congelado',
        ];

        foreach ($names as $name) {
            Product::firstOrCreate(
                ['name' => $name],
                [
                    'species_id' => $species->random()->id,
                    'capture_zone_id' => $zones->random()->id,
                    'family_id' => $family?->id,
                    'article_gtin' => $faker->optional(0.7)->numerify('84#############'),
                    'box_gtin' => $faker->optional(0.7)->numerify('84#############'),
                    'pallet_gtin' => $faker->optional(0.7)->numerify('84#############'),
                    'facil_com_code' => $faker->optional(0.5)->bothify('???###'),
                ]
            );
        }

        // Solo añadir productos extra si no hay suficientes (evitar duplicados en re-ejecución)
        $currentCount = Product::count();
        if ($currentCount >= 12) {
            $this->command->info('ProductSeeder: Ya existen suficientes productos. Omitiendo productos extra.');
            return;
        }
        $extraToCreate = min(5, 12 - $currentCount);
        for ($i = 0; $i < $extraToCreate; $i++) {
            Product::firstOrCreate(
                ['name' => 'Producto desarrollo ' . $faker->unique()->numerify('P###')],
                [
                    'species_id' => $species->random()->id,
                    'capture_zone_id' => $zones->random()->id,
                    'family_id' => $family?->id,
                    'article_gtin' => $faker->optional(0.6)->numerify('84#############'),
                    'box_gtin' => null,
                    'pallet_gtin' => null,
                    'facil_com_code' => null,
                ]
            );
        }
    }
}
