<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

/**
 * Impuestos para líneas de pedido (order_planned_product_details.tax_id).
 * Fuente: Análisis esquema tenant / desarrollo.
 * Depende de: ninguno.
 */
class TaxSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            ['name' => 'IVA 21%', 'rate' => 21.00],
            ['name' => 'IVA 10%', 'rate' => 10.00],
            ['name' => 'IVA 4%', 'rate' => 4.00],
            ['name' => 'Exento', 'rate' => 0.00],
        ];

        foreach ($taxes as $tax) {
            Tax::firstOrCreate(
                ['name' => $tax['name']],
                ['rate' => $tax['rate']]
            );
        }
    }
}
