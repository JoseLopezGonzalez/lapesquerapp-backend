<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantBaseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductCategorySeeder::class,
            ProspectCategorySeeder::class,
            ProductFamilySeeder::class,
            CaptureZonesSeeder::class,
            FishingGearSeeder::class,
            SpeciesSeeder::class,
            CalibersSeeder::class,
            ProcessSeeder::class,
            CountriesSeeder::class,
            PaymentTermsSeeder::class,
            IncotermsSeeder::class,
            TransportsSeeder::class,
            TaxSeeder::class,
            FAOZonesSeeder::class,
        ]);
    }
}
