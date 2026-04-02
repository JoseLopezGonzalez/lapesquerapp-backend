<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;
    use BuildsOperationsScenario;

    private ?string $token = null;
    private ?string $tenantSubdomain = null;
    private array $salesContext = [];

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $result = $this->createTenantAndAdminUser('customer');
        $this->tenantSubdomain = $result['slug'];
        $this->token = $result['token'];
        $this->salesContext = $this->createSalesContext('Customer');
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_list_customers(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/customers');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_search_customers_by_search_param(): void
    {
        Customer::query()->delete();

        $ctx = $this->salesContext;
        $needle = 'Mer-' . uniqid();

        $match = Customer::factory()->create([
            'name' => 'Cliente ' . $needle,
            'payment_term_id' => $ctx['paymentTerm']->id,
            'salesperson_id' => $ctx['salesperson']->id,
            'country_id' => $ctx['country']->id,
            'transport_id' => $ctx['transport']->id,
        ]);
        $otherName = 'Cliente Otro ' . uniqid();
        Customer::factory()->create([
            'name' => $otherName,
            'payment_term_id' => $ctx['paymentTerm']->id,
            'salesperson_id' => $ctx['salesperson']->id,
            'country_id' => $ctx['country']->id,
            'transport_id' => $ctx['transport']->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/customers?search=' . urlencode($needle) . '&perPage=50');

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $match->id, 'name' => $match->name]);
        $response->assertJsonMissing(['name' => $otherName]);
    }

    public function test_can_create_customer(): void
    {
        $ctx = $this->salesContext;
        $name = 'Cliente Test ' . uniqid();
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/customers', [
                'name' => $name,
                'vatNumber' => 'B' . uniqid(),
                'billing_address' => 'Dir fact test',
                'shipping_address' => 'Dir env test',
                'payment_term_id' => $ctx['paymentTerm']->id,
                'salesperson_id' => $ctx['salesperson']->id,
                'country_id' => $ctx['country']->id,
                'transport_id' => $ctx['transport']->id,
                'emails' => ['noreply@github.com'],
                'contact_info' => 'Contacto test',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'name']]);
        $this->assertDatabaseHas('customers', ['name' => $name], 'tenant');
    }

    public function test_can_show_customer(): void
    {
        $customer = $this->createCustomerForTest($this->salesContext);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/customers/' . $customer->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $customer->id);
        $response->assertJsonPath('data.name', $customer->name);
    }

    public function test_can_update_customer(): void
    {
        $customer = $this->createCustomerForTest($this->salesContext);
        $newName = 'Cliente Actualizado ' . uniqid();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/customers/' . $customer->id, [
                'name' => $newName,
                'vatNumber' => $customer->vat_number,
                'billing_address' => $customer->billing_address,
                'shipping_address' => $customer->shipping_address,
                'contact_info' => $customer->contact_info,
                'payment_term_id' => $customer->payment_term_id,
                'salesperson_id' => $customer->salesperson_id,
                'country_id' => $customer->country_id,
                'transport_id' => $customer->transport_id,
                'emails' => ['noreply@github.com'],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', $newName);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => $newName], 'tenant');
    }

    public function test_can_destroy_customer_without_orders(): void
    {
        $customer = $this->createCustomerForTest($this->salesContext);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/customers/' . $customer->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id], 'tenant');
    }

    public function test_can_get_customer_order_history_ranges(): void
    {
        $ctx = $this->salesContext;
        $customer = $this->createCustomerForTest($ctx);

        $baseOrderData = [
            'customer_id'      => $customer->id,
            'payment_term_id'  => $ctx['paymentTerm']->id,
            'salesperson_id'   => $ctx['salesperson']->id,
            'transport_id'     => $ctx['transport']->id,
            'billing_address'  => 'Dir fact',
            'shipping_address' => 'Dir env',
            'emails'           => 'ventas@test.com',
            'status'           => 'pending',
        ];

        Order::create(array_merge($baseOrderData, ['entry_date' => '2024-03-01', 'load_date' => '2024-03-15']));
        Order::create(array_merge($baseOrderData, ['entry_date' => '2024-08-01', 'load_date' => '2024-08-20']));
        Order::create(array_merge($baseOrderData, ['entry_date' => '2025-01-01', 'load_date' => '2025-01-10']));

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/customers/' . $customer->id . '/order-history/ranges');

        $response->assertStatus(200);
        $response->assertJsonPath('data.first_order_date', '2024-03-15');
        $response->assertJsonPath('data.last_order_date', '2025-01-10');
        $response->assertJsonPath('data.available_years', [2025, 2024]);
        $response->assertJsonPath('data.available_months_by_year.2025', [1]);
        $response->assertJsonPath('data.available_months_by_year.2024', [3, 8]);
    }

    public function test_get_customer_order_history_ranges_returns_empty_when_no_orders(): void
    {
        $customer = $this->createCustomerForTest($this->salesContext);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/customers/' . $customer->id . '/order-history/ranges');

        $response->assertStatus(200);
        $response->assertJsonPath('data.first_order_date', null);
        $response->assertJsonPath('data.last_order_date', null);
        $response->assertJsonPath('data.available_years', []);
        $response->assertJsonPath('data.available_months_by_year', []);
    }
}
