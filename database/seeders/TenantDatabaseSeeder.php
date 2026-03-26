<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantBaseCatalogSeeder::class,
            TenantBaseActorsSeeder::class,
            TenantCompanySettingsSeeder::class,
            TenantOperationsSeeder::class,
            TenantCrmDevSeeder::class,
            TenantRoutesDevSeeder::class,
            TenantProductionDevSeeder::class,
        ]);
    }
}
