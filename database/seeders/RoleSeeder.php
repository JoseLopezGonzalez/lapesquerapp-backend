<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Los roles están fijos en código (App\Enums\Role). Ya no se siembran en BD.
     */
    public function run(): void
    {
        // No-op: roles definidos como enum
    }
}
