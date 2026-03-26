<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantRoutesDevSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantRouteTemplatesSeeder::class,
            TenantDeliveryRoutesSeeder::class,
        ]);
    }
}
