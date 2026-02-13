<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Proveedores de desarrollo ? entorno tipo producci?n.
 * Inspirado en patrones reales: cebo_export_type (facilcom/a3erp), type (raw_material o vac?o),
 * combinaciones de facil_com_code, a3erp_cebo_code, facilcom_cebo_code; algunos sin contacto.
 * Datos generados con Faker (no datos reales). Idempotente: firstOrCreate por nombre, solo añade los que no existan.
 */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');
        $faker->seed(4301);

        // Patrones inspirados en producci?n: facilcom (con/sin c?digos), a3erp (con c?digos), type raw_material o ''
        $patterns = [
            ['cebo_export_type' => 'facilcom', 'facil_com_code' => true, 'facilcom_cebo_code' => true, 'type' => 'raw_material'],
            ['cebo_export_type' => 'facilcom', 'facil_com_code' => true, 'facilcom_cebo_code' => true, 'type' => 'raw_material'],
            ['cebo_export_type' => 'facilcom', 'facil_com_code' => true, 'facilcom_cebo_code' => true, 'type' => ''],
            ['cebo_export_type' => 'facilcom', 'facil_com_code' => false, 'facilcom_cebo_code' => false, 'type' => 'raw_material'],
            ['cebo_export_type' => 'facilcom', 'facil_com_code' => true, 'facilcom_cebo_code' => true, 'type' => ''],
            ['cebo_export_type' => 'a3erp', 'facil_com_code' => true, 'a3erp_cebo_code' => true, 'type' => 'raw_material'],
            ['cebo_export_type' => 'a3erp', 'facil_com_code' => true, 'a3erp_cebo_code' => true, 'type' => 'raw_material'],
            ['cebo_export_type' => 'facilcom', 'facil_com_code' => true, 'facilcom_cebo_code' => false, 'type' => ''],
            ['cebo_export_type' => 'facilcom', 'facil_com_code' => false, 'facilcom_cebo_code' => false, 'type' => 'raw_material'],
            ['cebo_export_type' => 'a3erp', 'facil_com_code' => true, 'a3erp_cebo_code' => true, 'type' => 'raw_material'],
        ];

        foreach ($patterns as $i => $pattern) {
            $name = 'Proveedor desarrollo ' . ($i + 1);
            $facilCom = $pattern['facil_com_code'] ?? false ? (string) $faker->numberBetween(1, 99) : null;
            $facilcomCebo = isset($pattern['facilcom_cebo_code']) && $pattern['facilcom_cebo_code']
                ? (string) $faker->numberBetween(1, 99)
                : null;
            $a3erpCebo = isset($pattern['a3erp_cebo_code']) && $pattern['a3erp_cebo_code']
                ? $faker->numerify('######')
                : null;

            Supplier::firstOrCreate(
                ['name' => $name],
                [
                    'cebo_export_type' => $pattern['cebo_export_type'],
                    'facil_com_code' => $facilCom,
                    'a3erp_cebo_code' => $pattern['cebo_export_type'] === 'a3erp' ? $a3erpCebo : null,
                    'facilcom_cebo_code' => $pattern['cebo_export_type'] === 'facilcom' ? $facilcomCebo : null,
                    'type' => $pattern['type'],
                    'contact_person' => $faker->optional(0.25)->name(),
                    'phone' => $faker->optional(0.2)->phoneNumber(),
                    'emails' => $faker->optional(0.2)->passthrough($faker->companyEmail() . '; CC:' . $faker->companyEmail()),
                    'address' => $faker->optional(0.2)->address(),
                ]
            );
        }
    }
}
