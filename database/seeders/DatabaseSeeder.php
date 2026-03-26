<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the central SaaS database.
     */
    public function run(): void
    {
        $this->call(CentralDevBootstrapSeeder::class);
    }
}
