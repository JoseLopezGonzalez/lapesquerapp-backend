<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TenantBaseCatalogSeeder::class,
        ]);
    }
}
