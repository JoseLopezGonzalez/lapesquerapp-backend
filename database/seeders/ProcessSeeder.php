<?php

namespace Database\Seeders;

use App\Models\Process;
use Illuminate\Database\Seeder;

/**
 * Procesos de producción de ejemplo para entorno de desarrollo.
 *
 * Crea un conjunto reducido pero realista de procesos
 * para trabajar con el módulo de producción:
 * - starting: procesos de entrada (recepción / clasificación)
 * - process: procesos intermedios (fileteado, limpieza, etc.)
 * - final: procesos finales (envasado, congelado, etc.)
 *
 * Nota: no asigna species_id para evitar dependencias
 * de conexión/tenant en contexto de consola.
 */
class ProcessSeeder extends Seeder
{
    public function run(): void
    {
        $processes = [
            // Procesos de inicio (starting)
            [
                'name' => 'Recepción materia prima',
                'type' => 'starting',
            ],
            [
                'name' => 'Clasificación por calibre',
                'type' => 'starting',
            ],

            // Procesos intermedios (process)
            [
                'name' => 'Eviscerado',
                'type' => 'process',
            ],
            [
                'name' => 'Limpieza y lavado',
                'type' => 'process',
            ],
            [
                'name' => 'Fileteado',
                'type' => 'process',
            ],

            // Procesos finales (final)
            [
                'name' => 'Envasado al vacío',
                'type' => 'final',
            ],
            [
                'name' => 'Congelado túnel',
                'type' => 'final',
            ],
            [
                'name' => 'Envasado bolsa retail',
                'type' => 'final',
            ],
        ];

        foreach ($processes as $data) {
            Process::updateOrCreate(
                [
                    'name' => $data['name'],
                ],
                [
                    'type' => $data['type'],
                ]
            );
        }
    }
}

