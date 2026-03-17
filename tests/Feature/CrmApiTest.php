<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CaptureZone;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Incoterm;
use App\Models\Offer;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Prospect;
use App\Models\Salesperson;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class CrmApiTest extends TestCase
{
    use ConfiguresTenantConnection;
    use RefreshDatabase;

    private string $tenantSubdomain;

    private string $commercialToken;

    private string $otherCommercialToken;

    private string $adminToken;

    private Salesperson $commercialSalesperson;

    private Salesperson $otherSalesperson;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantAndActors();
    }

    public function test_commercial_can_create_list_and_is_scoped_on_prospects(): void
    {
        $country = Country::firstOrCreate(['name' => 'Espana CRM']);

        $create = $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/prospects', [
                'companyName' => 'Acme Prospect',
                'countryId' => $country->id,
                'origin' => 'direct',
                'notes' => 'Primer contacto',
                'primaryContact' => [
                    'name' => 'Ana Compras',
                    'email' => 'ana@acme.test',
                    'phone' => '600111222',
                ],
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.companyName', 'Acme Prospect')
            ->assertJsonPath('data.salesperson.id', $this->commercialSalesperson->id)
            ->assertJsonPath('warnings', []);

        $prospectId = $create->json('data.id');

        $otherProspect = Prospect::create([
            'company_name' => 'Hidden Prospect',
            'salesperson_id' => $this->otherSalesperson->id,
            'status' => Prospect::STATUS_NEW,
            'origin' => Prospect::ORIGIN_OTHER,
        ]);

        $this->withHeaders($this->commercialHeaders())
            ->getJson('/api/v2/prospects')
            ->assertStatus(200)
            ->assertJsonFragment(['companyName' => 'Acme Prospect'])
            ->assertJsonMissing(['companyName' => 'Hidden Prospect']);

        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v2/prospects')
            ->assertStatus(200)
            ->assertJsonFragment(['companyName' => 'Acme Prospect'])
            ->assertJsonFragment(['companyName' => 'Hidden Prospect']);

        $this->withHeaders($this->commercialHeaders())
            ->getJson('/api/v2/prospects/'.$otherProspect->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('prospect_contacts', [
            'prospect_id' => $prospectId,
            'name' => 'Ana Compras',
            'is_primary' => 1,
        ], 'tenant');
    }

    public function test_prospect_store_returns_duplicate_warnings_without_blocking(): void
    {
        $country = Country::firstOrCreate(['name' => 'Portugal CRM']);
        Customer::create([
            'name' => 'Duplicados SA',
            'salesperson_id' => $this->commercialSalesperson->id,
            'country_id' => $country->id,
            'emails' => "same@test.com;\n",
            'contact_info' => 'Tel: 999999999',
        ]);

        $response = $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/prospects', [
                'companyName' => 'Duplicados SA',
                'countryId' => $country->id,
                'primaryContact' => [
                    'name' => 'Luis',
                    'email' => 'same@test.com',
                    'phone' => '999999999',
                ],
            ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('warnings'));
    }

    public function test_interaction_updates_prospect_last_contact_and_next_action(): void
    {
        $prospect = Prospect::create([
            'company_name' => 'Interact Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_NEW,
            'origin' => Prospect::ORIGIN_DIRECT,
        ]);

        $response = $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/commercial-interactions', [
                'prospectId' => $prospect->id,
                'type' => 'call',
                'occurredAt' => now()->subHour()->toISOString(),
                'summary' => 'Llamada de seguimiento',
                'result' => 'pending',
                'nextActionNote' => 'Enviar oferta',
                'nextActionAt' => now()->addDay()->format('Y-m-d'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.prospectId', $prospect->id)
            ->assertJsonPath('data.type', 'call');

        $prospect->refresh();
        $this->assertNotNull($prospect->last_contact_at);
        $this->assertEquals(now()->addDay()->format('Y-m-d'), $prospect->next_action_at?->format('Y-m-d'));
    }

    public function test_interaction_requires_exactly_one_target(): void
    {
        $prospect = Prospect::create([
            'company_name' => 'Invalid Interaction Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_NEW,
            'origin' => Prospect::ORIGIN_DIRECT,
        ]);
        $customer = Customer::create([
            'name' => 'Invalid Interaction Customer',
            'salesperson_id' => $this->commercialSalesperson->id,
            'emails' => 'invalid@test.com;',
            'contact_info' => 'Contacto',
        ]);

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/commercial-interactions', [
                'prospectId' => $prospect->id,
                'customerId' => $customer->id,
                'type' => 'call',
                'occurredAt' => now()->toISOString(),
                'summary' => 'No valido',
                'result' => 'pending',
            ])
            ->assertStatus(422);
    }

    public function test_prospect_can_be_converted_to_customer(): void
    {
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => '30 dias CRM']);
        $prospect = Prospect::create([
            'company_name' => 'Convert Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_OFFER_SENT,
            'origin' => Prospect::ORIGIN_DIRECT,
        ]);
        $prospect->contacts()->create([
            'name' => 'Marta',
            'phone' => '612123123',
            'email' => 'marta@convert.test',
            'is_primary' => true,
        ]);
        $prospect->offers()->create([
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Offer::STATUS_ACCEPTED,
            'payment_term_id' => $paymentTerm->id,
            'currency' => 'EUR',
        ]);

        $response = $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/prospects/'.$prospect->id.'/convert-to-customer');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Convert Prospect');

        $prospect->refresh();
        $this->assertEquals(Prospect::STATUS_CUSTOMER, $prospect->status);
        $this->assertNotNull($prospect->customer_id);
        $this->assertDatabaseHas('customers', [
            'id' => $prospect->customer_id,
            'name' => 'Convert Prospect',
            'payment_term_id' => $paymentTerm->id,
        ], 'tenant');
        $this->assertDatabaseHas('offers', [
            'prospect_id' => null,
            'customer_id' => $prospect->customer_id,
        ], 'tenant');
    }

    public function test_offer_lifecycle_and_create_order_from_offer(): void
    {
        $deps = $this->createOfferDependencies();
        $prospect = Prospect::create([
            'company_name' => 'Offer Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_NEW,
            'origin' => Prospect::ORIGIN_DIRECT,
        ]);
        $prospect->contacts()->create([
            'name' => 'Paula',
            'email' => 'paula@offer.test',
            'phone' => '611222333',
            'is_primary' => true,
        ]);

        $create = $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers', [
                'prospectId' => $prospect->id,
                'validUntil' => now()->addWeek()->format('Y-m-d'),
                'incotermId' => $deps['incoterm']->id,
                'paymentTermId' => $deps['paymentTerm']->id,
                'currency' => 'EUR',
                'notes' => 'Oferta inicial',
                'lines' => [[
                    'productId' => $deps['product']->id,
                    'description' => 'Langostino 2kg',
                    'quantity' => 10,
                    'unit' => 'kg',
                    'unitPrice' => 12.5,
                    'taxId' => $deps['tax']->id,
                    'boxes' => 4,
                    'currency' => 'EUR',
                ]],
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.status', Offer::STATUS_DRAFT);

        $offerId = $create->json('data.id');

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers/'.$offerId.'/send', [
                'channel' => 'pdf',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', Offer::STATUS_SENT);

        $prospect->refresh();
        $this->assertEquals(Prospect::STATUS_OFFER_SENT, $prospect->status);

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers/'.$offerId.'/accept')
            ->assertStatus(200)
            ->assertJsonPath('data.status', Offer::STATUS_ACCEPTED);

        $createOrder = $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers/'.$offerId.'/create-order', [
                'entryDate' => now()->format('Y-m-d'),
                'loadDate' => now()->addDay()->format('Y-m-d'),
                'transport' => $deps['transport']->id,
                'buyerReference' => 'CRM-REF-1',
            ]);

        $createOrder->assertStatus(201)
            ->assertJsonPath('data.buyerReference', 'CRM-REF-1')
            ->assertJsonPath('data.offerId', $offerId);

        $this->assertDatabaseHas('offers', [
            'id' => $offerId,
            'prospect_id' => null,
            'customer_id' => $createOrder->json('data.customer.id'),
            'order_id' => $createOrder->json('data.id'),
        ], 'tenant');
        $this->assertDatabaseHas('orders', [
            'id' => $createOrder->json('data.id'),
            'salesperson_id' => $this->commercialSalesperson->id,
        ], 'tenant');
        $this->assertDatabaseHas('order_planned_product_details', [
            'order_id' => $createOrder->json('data.id'),
            'product_id' => $deps['product']->id,
            'boxes' => 4,
        ], 'tenant');
    }

    public function test_offer_requires_exactly_one_target(): void
    {
        $deps = $this->createOfferDependencies();
        $prospect = Prospect::create([
            'company_name' => 'Offer Target Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_NEW,
            'origin' => Prospect::ORIGIN_DIRECT,
        ]);
        $customer = Customer::create([
            'name' => 'Offer Target Customer',
            'salesperson_id' => $this->commercialSalesperson->id,
            'emails' => 'target@test.com;',
            'contact_info' => 'Contacto',
        ]);

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers', [
                'prospectId' => $prospect->id,
                'customerId' => $customer->id,
                'currency' => 'EUR',
                'lines' => [[
                    'productId' => $deps['product']->id,
                    'description' => 'Linea',
                    'quantity' => 1,
                    'unit' => 'kg',
                    'unitPrice' => 10,
                    'taxId' => $deps['tax']->id,
                    'boxes' => 1,
                ]],
            ])
            ->assertStatus(422);
    }

    public function test_primary_contact_switches_when_new_primary_is_created(): void
    {
        $prospect = Prospect::create([
            'company_name' => 'Primary Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_NEW,
            'origin' => Prospect::ORIGIN_DIRECT,
        ]);

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/prospects/'.$prospect->id.'/contacts', [
                'name' => 'Contacto Uno',
                'email' => 'uno@test.com',
                'isPrimary' => true,
            ])
            ->assertStatus(201);

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/prospects/'.$prospect->id.'/contacts', [
                'name' => 'Contacto Dos',
                'email' => 'dos@test.com',
                'isPrimary' => true,
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('prospect_contacts', 2, 'tenant');
        $this->assertSame(1, $prospect->contacts()->where('is_primary', true)->count());
        $this->assertDatabaseHas('prospect_contacts', [
            'prospect_id' => $prospect->id,
            'name' => 'Contacto Dos',
            'is_primary' => 1,
        ], 'tenant');
    }

    public function test_dashboard_includes_interactions_scheduled_for_today_in_reminders_today(): void
    {
        $prospect = Prospect::create([
            'company_name' => 'Today Interaction Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_FOLLOWING,
            'origin' => Prospect::ORIGIN_DIRECT,
        ]);

        $prospect->interactions()->create([
            'salesperson_id' => $this->commercialSalesperson->id,
            'type' => 'email',
            'occurred_at' => now()->subHour(),
            'summary' => 'Recordatorio de hoy',
            'result' => 'pending',
            'next_action_at' => now()->format('Y-m-d'),
        ]);

        $response = $this->withHeaders($this->commercialHeaders())
            ->getJson('/api/v2/crm/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.counters.remindersToday', 1)
            ->assertJsonFragment([
                'type' => 'interaction',
                'label' => 'Today Interaction Prospect',
            ]);
    }

    public function test_accepted_offer_cannot_be_rejected(): void
    {
        $deps = $this->createOfferDependencies();
        $prospect = Prospect::create([
            'company_name' => 'Accepted Offer Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_NEW,
            'origin' => Prospect::ORIGIN_DIRECT,
        ]);

        $create = $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers', [
                'prospectId' => $prospect->id,
                'currency' => 'EUR',
                'lines' => [[
                    'productId' => $deps['product']->id,
                    'description' => 'Linea',
                    'quantity' => 1,
                    'unit' => 'kg',
                    'unitPrice' => 10,
                    'taxId' => $deps['tax']->id,
                    'boxes' => 1,
                ]],
            ]);

        $offerId = $create->json('data.id');

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers/'.$offerId.'/send', ['channel' => 'pdf'])
            ->assertStatus(200);

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers/'.$offerId.'/accept')
            ->assertStatus(200);

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers/'.$offerId.'/reject', [
                'reason' => 'No debería dejar',
            ])
            ->assertStatus(422);
    }

    public function test_create_order_from_offer_fails_when_offer_is_already_linked(): void
    {
        $deps = $this->createOfferDependencies();
        $prospect = Prospect::create([
            'company_name' => 'Linked Offer Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_NEW,
            'origin' => Prospect::ORIGIN_DIRECT,
        ]);
        $prospect->contacts()->create([
            'name' => 'Paula',
            'email' => 'paula@linked.test',
            'phone' => '611222333',
            'is_primary' => true,
        ]);

        $create = $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers', [
                'prospectId' => $prospect->id,
                'currency' => 'EUR',
                'paymentTermId' => $deps['paymentTerm']->id,
                'lines' => [[
                    'productId' => $deps['product']->id,
                    'description' => 'Langostino',
                    'quantity' => 10,
                    'unit' => 'kg',
                    'unitPrice' => 12.5,
                    'taxId' => $deps['tax']->id,
                    'boxes' => 4,
                ]],
            ]);

        $offerId = $create->json('data.id');

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers/'.$offerId.'/send', ['channel' => 'pdf'])
            ->assertStatus(200);

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers/'.$offerId.'/accept')
            ->assertStatus(200);

        $payload = [
            'entryDate' => now()->format('Y-m-d'),
            'loadDate' => now()->addDay()->format('Y-m-d'),
            'transport' => $deps['transport']->id,
        ];

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers/'.$offerId.'/create-order', $payload)
            ->assertStatus(201);

        $this->withHeaders($this->commercialHeaders())
            ->postJson('/api/v2/offers/'.$offerId.'/create-order', $payload)
            ->assertStatus(422);
    }

    public function test_dashboard_returns_expected_sections_for_commercial(): void
    {
        $country = Country::firstOrCreate(['name' => 'Dashboard CRM']);

        $todayProspect = Prospect::create([
            'company_name' => 'Today Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_NEW,
            'origin' => Prospect::ORIGIN_DIRECT,
            'next_action_at' => now()->format('Y-m-d'),
        ]);

        $staleProspect = Prospect::create([
            'company_name' => 'Stale Prospect',
            'salesperson_id' => $this->commercialSalesperson->id,
            'status' => Prospect::STATUS_FOLLOWING,
            'origin' => Prospect::ORIGIN_DIRECT,
            'last_contact_at' => now()->subDays(10),
        ]);

        $customer = Customer::create([
            'name' => 'Inactive Customer',
            'salesperson_id' => $this->commercialSalesperson->id,
            'country_id' => $country->id,
            'emails' => 'inactive@test.com;',
            'contact_info' => 'Contacto',
        ]);

        $otherCustomer = Customer::create([
            'name' => 'Other Customer',
            'salesperson_id' => $this->otherSalesperson->id,
            'country_id' => $country->id,
            'emails' => 'other@test.com;',
            'contact_info' => 'Otro',
        ]);

        $todayProspect->interactions()->create([
            'salesperson_id' => $this->commercialSalesperson->id,
            'type' => 'email',
            'occurred_at' => now()->subDay(),
            'summary' => 'Pendiente vencida',
            'result' => 'pending',
            'next_action_at' => now()->subDay()->format('Y-m-d'),
        ]);

        $staleProspect->interactions()->create([
            'salesperson_id' => $this->commercialSalesperson->id,
            'type' => 'call',
            'occurred_at' => now()->subHour(),
            'summary' => 'Interaccion de hoy',
            'result' => 'pending',
            'next_action_at' => now()->format('Y-m-d'),
        ]);

        $response = $this->withHeaders($this->commercialHeaders())
            ->getJson('/api/v2/crm/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.counters.remindersToday', 2)
            ->assertJsonPath('data.counters.inactiveCustomers', 1)
            ->assertJsonPath('data.counters.prospectsWithoutActivity', 1)
            ->assertJsonFragment(['companyName' => 'Stale Prospect'])
            ->assertJsonFragment(['name' => 'Inactive Customer'])
            ->assertJsonFragment(['type' => 'interaction', 'label' => 'Stale Prospect'])
            ->assertJsonMissing(['name' => 'Other Customer']);

        $this->assertNotNull($customer->id);
        $this->assertNotNull($otherCustomer->id);
    }

    public function test_existing_salespeople_options_and_settings_still_respect_commercial_scope(): void
    {
        Setting::query()->updateOrInsert(['key' => 'company.name'], ['value' => 'CRM Company']);
        Setting::query()->updateOrInsert(['key' => 'company.logo_url'], ['value' => 'logo.png']);
        Setting::query()->updateOrInsert(['key' => 'company.mail.password'], ['value' => 'secret']);

        $this->withHeaders($this->commercialHeaders())
            ->getJson('/api/v2/salespeople/options')
            ->assertStatus(200)
            ->assertExactJson([
                [
                    'id' => $this->commercialSalesperson->id,
                    'name' => $this->commercialSalesperson->name,
                ],
            ]);

        $this->withHeaders($this->commercialHeaders())
            ->getJson('/api/v2/settings')
            ->assertStatus(200)
            ->assertJsonFragment(['company.name' => 'CRM Company'])
            ->assertJsonFragment(['company.logo_url' => 'logo.png'])
            ->assertJsonMissing(['company.mail.password' => 'secret']);
    }

    private function createTenantAndActors(): void
    {
        $database = config('database.connections.'.config('database.default').'.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'crm-'.uniqid();

        Tenant::create([
            'name' => 'CRM Tenant',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $commercial = User::create([
            'name' => 'Commercial User',
            'email' => $slug.'-commercial@test.com',
            'role' => Role::Comercial->value,
        ]);
        $otherCommercial = User::create([
            'name' => 'Other Commercial',
            'email' => $slug.'-other@test.com',
            'role' => Role::Comercial->value,
        ]);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => $slug.'-admin@test.com',
            'role' => Role::Administrador->value,
        ]);

        $this->commercialSalesperson = Salesperson::create([
            'name' => 'Comercial Uno',
            'user_id' => $commercial->id,
        ]);
        $this->otherSalesperson = Salesperson::create([
            'name' => 'Comercial Dos',
            'user_id' => $otherCommercial->id,
        ]);

        $this->commercialToken = $commercial->createToken('test')->plainTextToken;
        $this->otherCommercialToken = $otherCommercial->createToken('test')->plainTextToken;
        $this->adminToken = $admin->createToken('test')->plainTextToken;
        $this->tenantSubdomain = $slug;
    }

    private function createOfferDependencies(): array
    {
        $country = Country::firstOrCreate(['name' => 'Italia CRM']);
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Pago contado CRM']);
        $incoterm = Incoterm::firstOrCreate(['code' => 'FOB'], ['description' => 'Free on Board']);
        $tax = Tax::firstOrCreate(['name' => 'IVA CRM'], ['rate' => 21]);
        $transport = \App\Models\Transport::firstOrCreate(
            ['name' => 'Transport CRM'],
            ['vat_number' => 'B'.uniqid(), 'address' => 'Street', 'emails' => 'transport@test.com']
        );
        $species = \App\Models\Species::factory()->create();
        $captureZone = CaptureZone::factory()->create();
        $product = Product::create([
            'name' => 'Producto CRM',
            'species_id' => $species->id,
            'capture_zone_id' => $captureZone->id,
            'article_gtin' => '8400000000001',
            'box_gtin' => '9400000000001',
            'pallet_gtin' => '9900000000001',
        ]);

        return compact('country', 'paymentTerm', 'incoterm', 'tax', 'transport', 'product');
    }

    private function commercialHeaders(): array
    {
        return $this->authHeaders($this->commercialToken);
    }

    private function adminHeaders(): array
    {
        return $this->authHeaders($this->adminToken);
    }

    private function authHeaders(string $token): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
