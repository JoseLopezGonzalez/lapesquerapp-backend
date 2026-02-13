<?php

namespace Database\Seeders;

use App\Models\RawMaterialReception;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Recepciones de materia prima de desarrollo — entorno tipo producción.
 * Inspirado en patrones reales: supplier_id, date, notes y totales declarados opcionales (null).
 * Solo añade hasta TARGET_RECEPTIONS; no borra datos existentes.
 */
class RawMaterialReceptionSeeder extends Seeder
{
    private const TARGET_RECEPTIONS = 10;

    public function run(): void
    {
        $suppliers = Supplier::all();
        if ($suppliers->isEmpty()) {
            $this->command->warn('RawMaterialReceptionSeeder: Ejecuta antes SupplierSeeder.');
            return;
        }

        $toCreate = max(0, self::TARGET_RECEPTIONS - RawMaterialReception::count());
        if ($toCreate === 0) {
            return;
        }

        $faker = Faker::create('es_ES');
        $faker->seed(5500);

        for ($i = 0; $i < $toCreate; $i++) {
            RawMaterialReception::create([
                'supplier_id' => $suppliers->random()->id,
                'date' => $faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
                'notes' => $faker->optional(0.25)->sentence(),
                'declared_total_amount' => null,
                'declared_total_net_weight' => null,
                'creation_mode' => null,
            ]);
        }
    }
}
