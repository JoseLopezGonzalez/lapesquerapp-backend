<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantProductionDevSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantProductionCostsSeeder::class,
            TenantProductionAdvancedSeeder::class,
            TenantIncidentsLabelsSeeder::class,
        ]);
    }
}
