<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\CaptureZone;
use App\Models\Country;
use App\Models\Customer;
use App\Models\FishingGear;
use App\Models\Incoterm;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Prospect;
use App\Models\Salesperson;
use App\Models\Species;
use App\Models\Tax;
use App\Models\Transport;
use App\Models\User;

trait BuildsCrmScenario
{
    protected function createCrmScenarioDependencies(
        ?Salesperson $primarySalesperson = null,
        ?Salesperson $secondarySalesperson = null,
        ?User $admin = null,
    ): array {
        $primarySalesperson ??= Salesperson::query()->whereNotNull('user_id')->first() ?? Salesperson::factory()->linkedUser()->create();
        $secondarySalesperson ??= Salesperson::query()->where('id', '!=', $primarySalesperson->id)->first()
            ?? Salesperson::factory()->linkedUser()->create();
        $admin ??= User::query()->where('role', Role::Administrador->value)->first()
            ?? User::factory()->state([
                'role' => Role::Administrador->value,
                'active' => true,
            ])->create();

        $country = Country::query()->firstOrCreate(['name' => 'Italia CRM']);
        $paymentTerm = PaymentTerm::query()->firstOrCreate(['name' => 'Pago contado CRM']);
        $incoterm = Incoterm::query()->firstOrCreate(
            ['code' => 'FOB'],
            ['description' => 'Free on Board']
        );
        $tax = Tax::query()->firstOrCreate(
            ['name' => 'IVA CRM'],
            ['rate' => 21]
        );
        $transport = Transport::query()->firstOrCreate(
            ['name' => 'Transport CRM'],
            [
                'vat_number' => 'BCRM00001',
                'address' => 'Muelle Comercial 1',
                'emails' => 'transport.crm@test.com;',
            ]
        );
        $product = Product::query()->first();
        if (! $product) {
            $fishingGear = FishingGear::query()->firstOrCreate(['name' => 'Arrastre CRM']);
            $species = Species::query()->firstOrCreate(
                ['name' => 'Especie CRM Base'],
                [
                    'scientific_name' => 'Species crm base',
                    'fao' => 'CRM',
                    'image' => 'https://example.test/species-crm.png',
                    'fishing_gear_id' => $fishingGear->id,
                ]
            );
            $captureZone = CaptureZone::factory()->create();

            $product = Product::create([
                'name' => 'Producto CRM Base',
                'species_id' => $species->id,
                'capture_zone_id' => $captureZone->id,
                'article_gtin' => '8400000000001',
                'box_gtin' => '9400000000001',
                'pallet_gtin' => '9900000000001',
            ]);
        }

        $mainProspect = Prospect::factory()
            ->assignedTo($primarySalesperson)
            ->following()
            ->create([
                'company_name' => 'CRM Prospect Main',
                'country_id' => $country->id,
                'next_action_at' => now()->addDays(2)->format('Y-m-d'),
                'next_action_note' => 'Llamar para avanzar oferta',
            ]);

        $secondaryProspect = Prospect::factory()
            ->assignedTo($secondarySalesperson)
            ->new()
            ->create([
                'company_name' => 'CRM Prospect Secondary',
                'country_id' => $country->id,
                'website' => 'https://crm-prospect-secondary.test',
                'next_action_at' => null,
                'next_action_note' => null,
            ]);

        $customer = Customer::factory()
            ->for($primarySalesperson, 'salesperson')
            ->create([
                'name' => 'CRM Customer Main',
                'country_id' => $country->id,
                'payment_term_id' => $paymentTerm->id,
                'transport_id' => $transport->id,
            ]);

        return compact(
            'primarySalesperson',
            'secondarySalesperson',
            'admin',
            'country',
            'paymentTerm',
            'incoterm',
            'tax',
            'transport',
            'product',
            'mainProspect',
            'secondaryProspect',
            'customer',
        );
    }
}
