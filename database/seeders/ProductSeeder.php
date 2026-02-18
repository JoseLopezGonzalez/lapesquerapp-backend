<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Species;
use App\Models\CaptureZone;
use App\Models\ProductFamily;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Productos de desarrollo (nombre, especie, zona captura, familia).
 * Fuente: backup_reduced.json tenant Brisamar — variantes reales: Pulpo Fresco -1kg/+1kg,
 * Eviscerado T3–T7, Caballa fresca/congelada, congelado en bloque. Depende: Species, CaptureZones, ProductFamily.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $species = Species::all();
        $zones = CaptureZone::all();
        $families = ProductFamily::all();
        $pulpo = $species->firstWhere('name', 'Pulpo común') ?? $species->firstWhere('name', 'Pulpo') ?? $species->first();
        $caballa = $species->firstWhere('name', 'Caballa') ?? $species->first();
        $merluza = $species->firstWhere('name', 'Merluza') ?? $species->first();
        $calamar = $species->firstWhere('name', 'Calamar') ?? $species->first();
        $sepia = $species->firstWhere('name', 'Sepia') ?? $species->first();
        $zone = $zones->first();
        $frescoEntero = $families->firstWhere('name', 'Fresco entero');
        $frescoEviscerado = $families->firstWhere('name', 'Fresco eviscerado');
        $congeladoEntero = $families->firstWhere('name', 'Congelado entero');
        $congeladoEviscerado = $families->firstWhere('name', 'Congelado eviscerado');

        if ($species->isEmpty() || $zones->isEmpty()) {
            $this->command->warn('ProductSeeder: Ejecuta antes SpeciesSeeder y CaptureZonesSeeder.');
            return;
        }

        // Variantes extraídas del backup: Pulpo Fresco, Eviscerado T3–T7, Caballa fresca/congelada, congelado en bloque
        $productsWithFamily = [
            ['name' => 'Pulpo Fresco -1kg', 'species_id' => $pulpo->id, 'family' => $frescoEntero],
            ['name' => 'Pulpo Fresco +1kg', 'species_id' => $pulpo->id, 'family' => $frescoEntero],
            ['name' => 'Pulpo Fresco +2kg', 'species_id' => $pulpo->id, 'family' => $frescoEntero],
            ['name' => 'Pulpo Fresco +3kg', 'species_id' => $pulpo->id, 'family' => $frescoEntero],
            ['name' => 'Pulpo Fresco Roto', 'species_id' => $pulpo->id, 'family' => $frescoEntero],
            ['name' => 'Pulpo Fresco Eviscerado T7', 'species_id' => $pulpo->id, 'family' => $frescoEviscerado],
            ['name' => 'Pulpo Fresco Eviscerado T6', 'species_id' => $pulpo->id, 'family' => $frescoEviscerado],
            ['name' => 'Pulpo Fresco Eviscerado T5', 'species_id' => $pulpo->id, 'family' => $frescoEviscerado],
            ['name' => 'Pulpo Fresco Eviscerado T4', 'species_id' => $pulpo->id, 'family' => $frescoEviscerado],
            ['name' => 'Pulpo Fresco Eviscerado T3', 'species_id' => $pulpo->id, 'family' => $frescoEviscerado],
            ['name' => 'Pulpo eviscerado congelado en bloque T7', 'species_id' => $pulpo->id, 'family' => $congeladoEviscerado],
            ['name' => 'Pulpo eviscerado congelado en bloque T6', 'species_id' => $pulpo->id, 'family' => $congeladoEviscerado],
            ['name' => 'Pulpo eviscerado congelado en bloque T5', 'species_id' => $pulpo->id, 'family' => $congeladoEviscerado],
            ['name' => 'Pulpo eviscerado congelado en bloque T4', 'species_id' => $pulpo->id, 'family' => $congeladoEviscerado],
            ['name' => 'Pulpo eviscerado congelado en bloque T3', 'species_id' => $pulpo->id, 'family' => $congeladoEviscerado],
            ['name' => 'Caballa fresca', 'species_id' => $caballa->id, 'family' => $frescoEntero],
            ['name' => 'Caballa congelada', 'species_id' => $caballa->id, 'family' => $congeladoEntero],
            ['name' => 'Caballa fresca pequeña', 'species_id' => $caballa->id, 'family' => $frescoEntero],
            ['name' => 'Caballa fresca mediana', 'species_id' => $caballa->id, 'family' => $frescoEntero],
            ['name' => 'Caballa fresca grande', 'species_id' => $caballa->id, 'family' => $frescoEntero],
            ['name' => 'Caballa congelada pequeña', 'species_id' => $caballa->id, 'family' => $congeladoEntero],
            ['name' => 'Caballa congelada mediana', 'species_id' => $caballa->id, 'family' => $congeladoEntero],
            ['name' => 'Caballa congelada grande', 'species_id' => $caballa->id, 'family' => $congeladoEntero],
        ];

        foreach ($productsWithFamily as $row) {
            $familyId = $row['family']?->id ?? $families->random()->id;
            Product::firstOrCreate(
                ['name' => $row['name']],
                [
                    'species_id' => $row['species_id'],
                    'capture_zone_id' => $zone->id,
                    'family_id' => $familyId,
                    'article_gtin' => $faker->optional(0.7)->numerify('84' . str_repeat('#', 11)),
                    // GTIN de caja: GTIN-14 (14 dígitos), prefijo 984 típico España para cajas (GS1).
                    'box_gtin' => $faker->optional(0.7)->numerify('984' . str_repeat('#', 11)),
                    'pallet_gtin' => null,
                    'facil_com_code' => $faker->optional(0.4)->numerify('##'),
                    'a3erp_code' => $faker->optional(0.3)->numerify('10###'),
                ]
            );
        }

        // Productos extra con nombres realistas (Merluza, Calamar, Sepia)
        $currentCount = Product::count();
        if ($currentCount >= 25) {
            $this->command->info('ProductSeeder: Ya existen suficientes productos. Omitiendo productos extra.');
            return;
        }
        $extraProducts = [
            ['name' => 'Merluza fresca', 'species' => $merluza, 'family' => $frescoEntero],
            ['name' => 'Merluza congelada', 'species' => $merluza, 'family' => $congeladoEntero],
            ['name' => 'Calamar fresco', 'species' => $calamar, 'family' => $frescoEntero],
            ['name' => 'Calamar congelado', 'species' => $calamar, 'family' => $congeladoEntero],
            ['name' => 'Sepia fresca', 'species' => $sepia, 'family' => $frescoEntero],
        ];
        foreach ($extraProducts as $row) {
            if (Product::count() >= 25) {
                break;
            }
            $familyId = $row['family']?->id ?? $families->random()->id;
            $speciesId = $row['species']?->id ?? $species->random()->id;
            Product::firstOrCreate(
                ['name' => $row['name']],
                [
                    'species_id' => $speciesId,
                    'capture_zone_id' => $zone->id,
                    'family_id' => $familyId,
                    'article_gtin' => $faker->optional(0.6)->numerify('84' . str_repeat('#', 11)),
                    'box_gtin' => null,
                    'pallet_gtin' => null,
                    'facil_com_code' => null,
                ]
            );
        }
    }
}
