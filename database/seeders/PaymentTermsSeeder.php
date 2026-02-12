<?php

namespace Database\Seeders;

use App\Models\PaymentTerm;
use Illuminate\Database\Seeder;

/**
 * Formas de pago (menú Clientes). Datos de desarrollo.
 */
class PaymentTermsSeeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            'Contado',
            '30 días',
            '60 días',
            '90 días',
            'Transferencia',
        ];

        foreach ($terms as $name) {
            PaymentTerm::firstOrCreate(['name' => $name]);
        }
    }
}
