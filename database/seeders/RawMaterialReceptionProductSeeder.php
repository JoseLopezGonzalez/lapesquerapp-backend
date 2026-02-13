<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\RawMaterialReception;
use App\Models\RawMaterialReceptionProduct;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Líneas de producto de recepciones de materia prima — entorno tipo producción.
 * Inspirado en patrones reales: reception_id, product_id, lot (null), net_weight (0.7–105 kg), price (null).
 * Solo añade líneas que no existan (firstOrCreate por reception_id + product_id + lot).
 */
class RawMaterialReceptionProductSeeder extends Seeder
{
    private const TARGET_LINES = 30;

    public function run(): void
    {
        $receptions = RawMaterialReception::all();
        $products = Product::all();

        if ($receptions->isEmpty() || $products->isEmpty()) {
            $this->command->warn('RawMaterialReceptionProductSeeder: Ejecuta antes RawMaterialReceptionSeeder y ProductSeeder.');
            return;
        }

        $toCreate = max(0, self::TARGET_LINES - RawMaterialReceptionProduct::count());
        if ($toCreate === 0) {
            return;
        }

        $faker = Faker::create('es_ES');
        $faker->seed(5510);

        $created = 0;
        $attempts = 0;

        while ($created < $toCreate && $attempts < $toCreate * 4) {
            $attempts++;
            $reception = $receptions->random();
            $product = $products->random();

            $exists = RawMaterialReceptionProduct::where('reception_id', $reception->id)
                ->where('product_id', $product->id)
                ->whereNull('lot')
                ->exists();
            if ($exists) {
                continue;
            }

            $netWeight = $faker->randomFloat(2, 0.7, 105);

            RawMaterialReceptionProduct::create([
                'reception_id' => $reception->id,
                'product_id' => $product->id,
                'lot' => null,
                'net_weight' => $netWeight,
                'price' => null,
            ]);
            $created++;
        }
    }
}
