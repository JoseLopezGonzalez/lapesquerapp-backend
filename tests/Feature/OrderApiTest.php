<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pallet;
use App\Models\PaymentTerm;
use App\Models\Salesperson;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?string $token = null;
    private ?string $tenantSubdomain = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantAndUser();
    }

    private function createTenantAndUser(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'order-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Order',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => $slug . '@test.com',
            'password' => bcrypt('password'),
            'role' => Role::Administrador->value,
        ]);

        $this->token = $user->createToken('test')->plainTextToken;
        $this->tenantSubdomain = $slug;
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    private function createOrderDependencies(): array
    {
        $country = Country::firstOrCreate(['name' => 'España']);
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado']);
        $transport = Transport::firstOrCreate(
            ['name' => 'Transport OrderApiTest ' . uniqid()],
            ['vat_number' => 'B' . uniqid(), 'address' => 'A', 'emails' => 't@t.com']
        );
        $salesperson = Salesperson::firstOrCreate(['name' => 'Comercial Order Test']);
        $customer = Customer::create([
            'name' => 'C ' . uniqid(),
            'vat_number' => 'B' . uniqid(),
            'payment_term_id' => $paymentTerm->id,
            'billing_address' => 'B',
            'shipping_address' => 'S',
            'salesperson_id' => $salesperson->id,
            'emails' => 'c@c.com',
            'contact_info' => 'C',
            'country_id' => $country->id,
            'transport_id' => $transport->id,
        ]);

        return compact('country', 'paymentTerm', 'transport', 'salesperson', 'customer');
    }

    private function createOrder(?array $deps = null): Order
    {
        $deps = $deps ?? $this->createOrderDependencies();

        return Order::create([
            'customer_id' => $deps['customer']->id,
            'entry_date' => now()->format('Y-m-d'),
            'load_date' => now()->addDay()->format('Y-m-d'),
            'payment_term_id' => $deps['paymentTerm']->id,
            'salesperson_id' => $deps['salesperson']->id,
            'transport_id' => $deps['transport']->id,
            'billing_address' => 'B',
            'shipping_address' => 'S',
            'emails' => 'test@test.com',
            'status' => 'pending',
        ]);
    }

    // ---- LIST ----

    public function test_can_list_orders(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/orders');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_list_orders_requires_auth(): void
    {
        $response = $this->withHeader('X-Tenant', $this->tenantSubdomain)
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/v2/orders');

        $response->assertStatus(401);
    }

    // ---- CREATE ----

    public function test_can_create_order(): void
    {
        $deps = $this->createOrderDependencies();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/orders', [
                'customer' => $deps['customer']->id,
                'entryDate' => now()->format('Y-m-d'),
                'loadDate' => now()->addDay()->format('Y-m-d'),
                'payment' => $deps['paymentTerm']->id,
                'salesperson' => $deps['salesperson']->id,
                'transport' => $deps['transport']->id,
                'billingAddress' => 'B',
                'shippingAddress' => 'S',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'status']]);
        $this->assertDatabaseHas('orders', ['status' => 'pending'], 'tenant');
    }

    public function test_create_order_returns_422_with_invalid_payload(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/orders', []);

        $response->assertStatus(422);
    }

    // ---- SHOW ----

    public function test_can_show_order(): void
    {
        $deps = $this->createOrderDependencies();
        $order = $this->createOrder($deps);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/orders/' . $order->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $order->id);
        $response->assertJsonPath('data.status', 'pending');
    }

    // ---- UPDATE ----

    public function test_can_update_order(): void
    {
        $deps = $this->createOrderDependencies();
        $order = $this->createOrder($deps);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/orders/' . $order->id, [
                'buyerReference' => 'REF-TEST-123',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.buyerReference', 'REF-TEST-123');
    }

    // ---- DESTROY ----

    public function test_can_destroy_order_without_pallets(): void
    {
        $deps = $this->createOrderDependencies();
        $order = $this->createOrder($deps);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/orders/' . $order->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('orders', ['id' => $order->id], 'tenant');
    }

    public function test_destroy_order_fails_when_has_pallets(): void
    {
        $deps = $this->createOrderDependencies();
        $order = $this->createOrder($deps);

        $pallet = new Pallet(['status' => Pallet::STATE_REGISTERED]);
        $pallet->order_id = $order->id;
        $pallet->save();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/orders/' . $order->id);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'No se puede eliminar el pedido porque está en uso']);
        $this->assertDatabaseHas('orders', ['id' => $order->id], 'tenant');
    }

    // ---- DESTROY MULTIPLE ----

    public function test_can_destroy_multiple_orders(): void
    {
        $deps = $this->createOrderDependencies();
        $order1 = $this->createOrder($deps);
        $order2 = $this->createOrder($deps);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/orders', [
                'ids' => [$order1->id, $order2->id],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('orders', ['id' => $order1->id], 'tenant');
        $this->assertDatabaseMissing('orders', ['id' => $order2->id], 'tenant');
    }

    public function test_destroy_multiple_orders_fails_when_some_have_pallets(): void
    {
        $deps = $this->createOrderDependencies();
        $order1 = $this->createOrder($deps);
        $order2 = $this->createOrder($deps);

        $pallet = new Pallet(['status' => Pallet::STATE_REGISTERED]);
        $pallet->order_id = $order1->id;
        $pallet->save();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/orders', [
                'ids' => [$order1->id, $order2->id],
            ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'No se pueden eliminar algunos pedidos porque están en uso']);
    }

    // ---- UPDATE STATUS ----

    public function test_can_update_order_status_to_finished(): void
    {
        $deps = $this->createOrderDependencies();
        $order = $this->createOrder($deps);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/orders/' . $order->id . '/status', [
                'status' => 'finished',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'finished');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'finished'], 'tenant');
    }

    // ---- OPTIONS ----

    public function test_can_get_orders_options(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/orders/options');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    }

    // ---- ACTIVE ----

    public function test_can_get_active_orders(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/orders/active');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_can_get_active_orders_options(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/active-orders/options');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    }
}
