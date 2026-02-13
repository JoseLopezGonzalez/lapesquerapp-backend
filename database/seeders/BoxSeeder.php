<?php

namespace Database\Seeders;

use App\Models\Box;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Cajas de desarrollo (lot, gs1_128, pesos).
 * Fuente: Análisis backup tenant Brisamar — formato lot DDMMYY/DDMMYYOCC#####, gs1_128 (01)GTIN(3100)peso(10)lot.
 * Depende de: Products.
 */
class BoxSeeder extends Seeder
{
    public function run(): void
    {
        if (Box::count() >= 30) {
            $this->command->info('BoxSeeder: Ya existen suficientes cajas. Omitiendo creación.');
            return;
        }

        $faker = Faker::create('es_ES');

        $products = Product::all();
        if ($products->isEmpty()) {
            $this->command->warn('BoxSeeder: Ejecuta antes ProductSeeder.');
            return;
        }

        $toCreate = 30 - Box::count();
        if ($toCreate <= 0) {
            return;
        }

        for ($i = 0; $i < $toCreate; $i++) {
            $product = $products->random();
            $netWeight = $faker->randomFloat(2, 5, 25);
            $grossWeight = $netWeight + $faker->randomFloat(2, 0.2, 1.5);

            // Formato lot realista (backup Brisamar): DDMMYY o DDMMYYOCC#####
            $useOcc = $faker->boolean(30);
            $lot = $faker->dateTimeBetween('-6 months', 'now')->format('dmy');
            if ($useOcc) {
                $lot .= 'OCC' . $faker->numerify('#####');
            }

            // GS1-128 opcional: (01)GTIN(3100)peso_centésimas(10)lot
            $gtin = $product->article_gtin ?? $faker->numerify('984366139#####');
            $weightCentesimas = (int) round($netWeight * 100);
            $gs1_128 = "(01){$gtin}(3100)" . str_pad((string) $weightCentesimas, 6, '0', STR_PAD_LEFT) . "(10){$lot}";

            Box::create([
                'article_id' => $product->id,
                'lot' => $lot,
                'gs1_128' => $gs1_128,
                'gross_weight' => $grossWeight,
                'net_weight' => $netWeight,
            ]);
        }
    }
}
