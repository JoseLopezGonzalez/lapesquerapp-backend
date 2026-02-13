<?php

namespace Database\Seeders;

use App\Models\CeboDispatch;
use App\Models\CeboDispatchProduct;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Lineas de producto de despachos de cebo. Inspirado en produccion.
 * Solo anade lineas que no existan (evita duplicar dispatch_id + product_id).
 */
class CeboDispatchProductSeeder extends Seeder
{
    private const TARGET_LINES = 25;

    public function run(): void
    {
        $dispatches = CeboDispatch::all();
        $products = Product::all();

        if ($dispatches->isEmpty() || $products->isEmpty()) {
            $this->command->warn('CeboDispatchProductSeeder: Ejecuta antes CeboDispatchSeeder y ProductSeeder.');
            return;
        }

        $toCreate = max(0, self::TARGET_LINES - CeboDispatchProduct::count());
        if ($toCreate === 0) {
            return;
        }

        $faker = Faker::create('es_ES');
        $faker->seed(5410);

        $created = 0;
        $attempts = 0;

        while ($created < $toCreate && $attempts < $toCreate * 3) {
            $attempts++;
            $dispatch = $dispatches->random();
            $product = $products->random();

            if (CeboDispatchProduct::where('dispatch_id', $dispatch->id)->where('product_id', $product->id)->exists()) {
                continue;
            }

            $netWeight = $faker->boolean(85)
                ? $faker->randomFloat(2, 20, 200)
                : $faker->randomFloat(2, 1, 19);

            CeboDispatchProduct::create([
                'dispatch_id' => $dispatch->id,
                'product_id' => $product->id,
                'net_weight' => $netWeight,
                'price' => null,
            ]);
            $created++;
        }
    }
}
