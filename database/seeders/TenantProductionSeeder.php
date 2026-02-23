<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Production seeder for new tenants (onboarding).
 * Only seeds catalogs and essential data — no demo/operational data.
 * Uses updateOrInsert to be idempotent (safe to re-run).
 */
class TenantProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $this->call(ProductCategorySeeder::class);
        $this->call(ProductFamilySeeder::class);
        $this->call(CaptureZonesSeeder::class);
        $this->call(FishingGearSeeder::class);
        $this->call(SpeciesSeeder::class);

        $this->call(CountriesSeeder::class);
        $this->call(PaymentTermsSeeder::class);
        $this->call(IncotermsSeeder::class);
        $this->call(TaxSeeder::class);
        $this->call(FAOZonesSeeder::class);

        $this->seedDefaultStore();
        $this->seedDefaultSettings();
    }

    private function seedDefaultStore(): void
    {
        $minimalMap = '{"posiciones":[{"id":1,"nombre":"U1","x":40,"y":40,"width":460,"height":238,"tipo":"center"}],"elementos":{"fondos":[{"x":0,"y":0,"width":665,"height":1510}],"textos":[]}}';

        DB::table('stores')->updateOrInsert(
            ['name' => 'Almacén Principal'],
            [
                'temperature' => -18.0,
                'capacity' => 50000.0,
                'map' => $minimalMap,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Seed initial company settings from central tenant data if available,
     * or with sensible defaults.
     */
    private function seedDefaultSettings(): void
    {
        $defaults = [
            'company.display_name' => '',
            'company.tax_id' => '',
            'company.address' => '',
            'company.city' => '',
            'company.postal_code' => '',
            'company.phone' => '',
            'company.email' => '',
            'company.logo_url' => '',
            'company.date_format' => 'd/m/Y',
            'company.currency' => 'EUR',
            'company.mail.mailer' => 'smtp',
            'company.mail.host' => '',
            'company.mail.port' => '587',
            'company.mail.encryption' => 'tls',
            'company.mail.username' => '',
            'company.mail.password' => '',
            'company.mail.from_address' => '',
            'company.mail.from_name' => '',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
