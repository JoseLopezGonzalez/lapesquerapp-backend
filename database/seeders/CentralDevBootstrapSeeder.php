<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Bootstrap central reproducible para entorno local/dev.
 */
class CentralDevBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SuperadminUserSeeder::class,
            FeatureFlagSeeder::class,
            CentralTenantsSeeder::class,
            DevTenantFeatureOverridesSeeder::class,
        ]);
    }
}
