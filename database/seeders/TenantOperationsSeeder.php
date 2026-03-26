<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
            RawMaterialReceptionSeeder::class,
            RawMaterialReceptionProductSeeder::class,
            CeboDispatchSeeder::class,
            CeboDispatchProductSeeder::class,
            OrderSeeder::class,
            OrderPlannedProductDetailSeeder::class,
            BoxSeeder::class,
            PalletSeeder::class,
            PalletBoxSeeder::class,
            StoredPalletSeeder::class,
            OrderPalletSeeder::class,
        ]);
    }
}
