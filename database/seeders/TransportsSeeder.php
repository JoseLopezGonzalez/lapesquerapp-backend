<?php

namespace Database\Seeders;

use App\Models\Transport;
use Illuminate\Database\Seeder;

/**
 * Transportes (menú). Datos de desarrollo.
 */
class TransportsSeeder extends Seeder
{
    public function run(): void
    {
        $transports = [
            ['name' => 'Transporte Demo S.L.', 'vat_number' => 'B12345678', 'address' => 'Calle Ejemplo 1', 'emails' => 'transporte@demo.local'],
        ];

        foreach ($transports as $data) {
            Transport::firstOrCreate(
                ['name' => $data['name']],
                [
                    'vat_number' => $data['vat_number'] ?? null,
                    'address' => $data['address'] ?? null,
                    'emails' => $data['emails'] ?? null,
                ]
            );
        }
    }
}
