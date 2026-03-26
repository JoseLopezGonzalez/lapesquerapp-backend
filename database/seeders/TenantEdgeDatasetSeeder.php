<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Dataset extremo para QA manual y reproducción de casos raros.
 * Parte del dataset extended y añade anomalías controladas.
 */
class TenantEdgeDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantExtendedDatasetSeeder::class,
            TenantEdgeCasesSeeder::class,
        ]);
    }
}
