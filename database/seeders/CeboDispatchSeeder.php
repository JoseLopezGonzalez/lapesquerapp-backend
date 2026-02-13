<?php

namespace Database\Seeders;

use App\Models\CeboDispatch;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Faker\Factory as Faker;

/**
 * Despachos de cebo de desarrollo — entorno tipo producción.
 * Inspirado en patrones reales: supplier_id, date, notes (opcional), export_type (facilcom/a3erp).
 * Solo añade hasta TARGET_DISPATCHES; no borra datos existentes.
 */
class CeboDispatchSeeder extends Seeder
{
    private const TARGET_DISPATCHES = 10;

    public function run(): void
    {
        $suppliers = Supplier::all();
        if ($suppliers->isEmpty()) {
            $this->command->warn('CeboDispatchSeeder: Ejecuta antes SupplierSeeder.');
            return;
        }

        $toCreate = max(0, self::TARGET_DISPATCHES - CeboDispatch::count());
        if ($toCreate === 0) {
            return;
        }

        $faker = Faker::create('es_ES');
        $faker->seed(5400);

        $hasExportType = Schema::hasColumn((new CeboDispatch)->getTable(), 'export_type');

        for ($i = 0; $i < $toCreate; $i++) {
            $data = [
                'supplier_id' => $suppliers->random()->id,
                'date' => $faker->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
                'notes' => $faker->optional(0.3)->sentence(),
            ];
            if ($hasExportType) {
                $data['export_type'] = $faker->randomElement(['facilcom', 'a3erp']);
            }
            CeboDispatch::create($data);
        }
    }
}
