<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Dataset ampliado para desarrollo local y demos internas.
 * Extiende el seed tenant base con más volumen y escenarios visuales.
 */
class TenantExtendedDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantDatabaseSeeder::class,
            TenantVolumeExpansionSeeder::class,
            TenantDemoUiSeeder::class,
        ]);
    }
}
