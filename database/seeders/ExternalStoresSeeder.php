<?php

namespace Database\Seeders;

use App\Models\ExternalUser;
use App\Models\Store;
use Illuminate\Database\Seeder;

class ExternalStoresSeeder extends Seeder
{
    private const MINIMAL_MAP = '{"posiciones":[{"id":1,"nombre":"U1","x":40,"y":40,"width":460,"height":238,"tipo":"center"}],"elementos":{"fondos":[{"x":0,"y":0,"width":665,"height":1510}],"textos":[]}}';

    public function run(): void
    {
        $primary = ExternalUser::where('email', 'maquila1@pesquerapp.test')->first();
        $secondary = ExternalUser::where('email', 'maquila2@pesquerapp.test')->first();

        $stores = [
            [
                'name' => 'Maquila Frío Sur - Cámara 1',
                'temperature' => -18.0,
                'capacity' => 18000.0,
                'store_type' => 'externo',
                'external_user_id' => $primary?->id,
            ],
            [
                'name' => 'Maquila Frío Sur - Expediciones',
                'temperature' => 2.0,
                'capacity' => 9000.0,
                'store_type' => 'externo',
                'external_user_id' => $primary?->id,
            ],
            [
                'name' => 'Maquila Costa Norte - Cámara',
                'temperature' => -20.0,
                'capacity' => 15000.0,
                'store_type' => 'externo',
                'external_user_id' => $secondary?->id,
            ],
        ];

        foreach ($stores as $store) {
            if (! $store['external_user_id']) {
                continue;
            }

            Store::updateOrCreate(
                ['name' => $store['name']],
                [
                    'temperature' => $store['temperature'],
                    'capacity' => $store['capacity'],
                    'map' => self::MINIMAL_MAP,
                    'store_type' => $store['store_type'],
                    'external_user_id' => $store['external_user_id'],
                ]
            );
        }
    }
}
