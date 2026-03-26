<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\Label;
use Database\Seeders\Concerns\SeedsTenantProductionData;
use Illuminate\Database\Seeder;

class TenantIncidentsLabelsSeeder extends Seeder
{
    use SeedsTenantProductionData;

    public function run(): void
    {
        $mercatiOrder = $this->productionOrder('Mercati Tirreno');
        $reteMareOrder = $this->productionOrder('Rete Mare Milano');

        Incident::query()->updateOrCreate(
            ['order_id' => $mercatiOrder->id],
            [
                'description' => 'Recepción con cajas dañadas y revisión pendiente.',
                'status' => Incident::STATUS_OPEN,
                'resolution_type' => null,
                'resolution_notes' => null,
                'resolved_at' => null,
            ]
        );

        Incident::query()->updateOrCreate(
            ['order_id' => $reteMareOrder->id],
            [
                'description' => 'Diferencia parcial en peso entregado.',
                'status' => Incident::STATUS_RESOLVED,
                'resolution_type' => Incident::RESOLUTION_TYPE_PARTIALLY_RETURNED,
                'resolution_notes' => 'Compensado parcialmente en la siguiente carga.',
                'resolved_at' => now()->subDay(),
            ]
        );

        $labels = [
            [
                'name' => 'Etiqueta caja estándar',
                'format' => ['width' => 50, 'height' => 30, 'mode' => 'box'],
            ],
            [
                'name' => 'Etiqueta palet logística',
                'format' => ['width' => 100, 'height' => 150, 'mode' => 'pallet'],
            ],
            [
                'name' => 'Etiqueta retail premium',
                'format' => ['width' => 60, 'height' => 40, 'mode' => 'retail'],
            ],
        ];

        foreach ($labels as $payload) {
            Label::query()->updateOrCreate(
                ['name' => $payload['name']],
                ['format' => $payload['format']]
            );
        }
    }
}
