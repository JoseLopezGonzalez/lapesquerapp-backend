<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantCrmDevSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantCrmProspectsSeeder::class,
            TenantCrmAgendaSeeder::class,
            TenantCrmOffersSeeder::class,
        ]);
    }
}
