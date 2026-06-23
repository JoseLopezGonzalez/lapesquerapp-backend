<?php

namespace Database\Seeders;

use App\Models\Transport;
use Illuminate\Database\Seeder;

class TransportsSeeder extends Seeder
{
    public function run(): void
    {
        $transports = [
            [
                'name'       => 'Transportes Marítimos del Sur S.L.',
                'vat_number' => 'B53001234',
                'address'    => "Calle Puerto, 12\n21001 Huelva (Huelva)",
                'emails'     => 'info@transportesmaritimossur.es;',
            ],
            [
                'name'       => 'Logística Costa S.L.U.',
                'vat_number' => 'B53005678',
                'address'    => "Avenida del Mar, 45\n11202 Algeciras (Cádiz)",
                'emails'     => 'logistica@logisticacosta.es;',
            ],
            [
                'name'       => 'Frío Express Andalucía S.L.',
                'vat_number' => 'B53009012',
                'address'    => "Polígono Industrial Sur, Nave 8\n41700 Dos Hermanas (Sevilla)",
                'emails'     => 'expediciones@frioexpressandalucia.es;',
            ],
            [
                'name'       => 'Carga y Distribución S.L.U.',
                'vat_number' => 'B53003456',
                'address'    => "Calle Almería, 3\n04004 Almería (Almería)",
                'emails'     => 'carga@cargadistribucion.es;',
            ],
            [
                'name'       => 'Transmediterráneo S.L.',
                'vat_number' => 'B53007890',
                'address'    => "Muelle de Poniente, 1\n46024 Valencia (Valencia)",
                'emails'     => 'contacto@transmediterraneo.es;',
            ],
        ];

        foreach ($transports as $data) {
            Transport::firstOrCreate(
                ['name' => $data['name']],
                [
                    'vat_number' => $data['vat_number'],
                    'address'    => $data['address'],
                    'emails'     => $data['emails'],
                ]
            );
        }
    }
}
