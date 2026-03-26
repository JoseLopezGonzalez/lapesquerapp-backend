<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production seeder for new tenants (onboarding).
 * Only seeds catalogs and essential data — no demo/operational data.
 * Uses updateOrInsert to be idempotent (safe to re-run).
 */
class TenantProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UsersSeeder::class,
            TenantBaseCatalogSeeder::class,
            SalespeopleSeeder::class,
            SupplierSeeder::class,
            StoreSeeder::class,
            TenantCompanySettingsSeeder::class,
        ]);
    }
}
