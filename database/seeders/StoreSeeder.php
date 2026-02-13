<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

/**
 * Almacenes / tiendas de desarrollo — entorno tipo producción.
 * Inspirado en patrones reales: name (Cámara, Container, Almacén), temperature (-25 a 4 °C), capacity (kg),
 * map (JSON mínimo con posiciones para que el mapa no falle).
 * Solo añade los que no existan (firstOrCreate por nombre).
 */
class StoreSeeder extends Seeder
{
    /** Mapa JSON mínimo: una posición U1 para desarrollo */
    private const MINIMAL_MAP = '{"posiciones":[{"id":1,"nombre":"U1","x":40,"y":40,"width":460,"height":238,"tipo":"center"}],"elementos":{"fondos":[{"x":0,"y":0,"width":665,"height":1510}],"textos":[]}}';

    public function run(): void
    {
        $stores = [
            ['name' => 'Cámara de congelación', 'temperature' => -18.0, 'capacity' => 100000.0],
            ['name' => 'Cámara de refrigeración', 'temperature' => 4.0, 'capacity' => 3000.0],
            ['name' => 'Container 1', 'temperature' => -18.0, 'capacity' => 34000.0],
            ['name' => 'Container 2', 'temperature' => -18.0, 'capacity' => 34000.0],
            ['name' => 'Almacén Norte', 'temperature' => -23.0, 'capacity' => 20000.0],
            ['name' => 'Almacén desarrollo', 'temperature' => -18.0, 'capacity' => 50000.0],
        ];

        foreach ($stores as $row) {
            Store::firstOrCreate(
                ['name' => $row['name']],
                [
                    'temperature' => $row['temperature'],
                    'capacity' => $row['capacity'],
                    'map' => self::MINIMAL_MAP,
                ]
            );
        }
    }
}
