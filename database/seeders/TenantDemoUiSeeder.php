<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Escenarios opcionales para demos/UI.
 */
class TenantDemoUiSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PalletTimelineSeeder::class,
        ]);
    }
}
