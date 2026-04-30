<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\CaptureZone;
use App\Models\FishingGear;
use App\Models\Order;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Species;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use BuildsOperationsScenario;
    use ConfiguresTenantConnection;
    use RefreshDatabase;

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
        $result = $this->createTenantAndAdminUser('order');
        $this->tenantSubdomain = $result['slug'];
        $this->token = $result['token'];
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ];
    }

    private function createOrderDependencies(): array
    {
        $context = $this->createSalesContext('Order');
        $customer = $this->createCustomerForTest($context, 'Order');

        return array_merge($context, compact('customer'));
    }

    private function createOrder(?array $deps = null): Order
    {
        $deps = $deps ?? $this->createOrderDependencies();

        return $this->createOrderForTest($deps['customer'], $deps);
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
            ->getJson('/api/v2/orders/'.$order->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $order->id);
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_show_order_uses_manual_box_cost_as_fallback(): void
    {
        $deps = $this->createOrderDependencies();
        $order = $this->createOrder($deps);

        $species = Species::factory()->create([
            'fishing_gear_id' => FishingGear::factory()->create()->id,
        ]);
        $product = Product::factory()->create([
            'family_id' => ProductFamily::factory()->create()->id,
            'species_id' => $species->id,
            'capture_zone_id' => CaptureZone::factory()->create()->id,
        ]);
        $pallet = Pallet::factory()->create([
            'order_id' => $order->id,
            'reception_id' => null,
            'status' => Pallet::STATE_SHIPPED,
        ]);
        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => 'LOT-MANUAL-ORDER',
            'net_weight' => 10,
            'gross_weight' => 10.5,
            'manual_cost_per_kg' => 2.75,
        ]);

        PalletBox::create(['pallet_id' => $pallet->id, 'box_id' => $box->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/orders/'.$order->id);

        $response->assertOk()
            ->assertJsonPath('data.pallets.0.boxes.0.manualCostPerKg', 2.75)
            ->assertJsonPath('data.pallets.0.boxes.0.costPerKg', 2.75)
            ->assertJsonPath('data.pallets.0.boxes.0.totalCost', 27.5)
            ->assertJsonPath('data.totalCost', 27.5)
            ->assertJsonPath('data.costPerKg', 2.75);

        $analysisResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/orders/'.$order->id.'/cost-analysis');

        $analysisResponse->assertOk()
            ->assertJsonPath('summary.totalCost', 27.5)
            ->assertJsonPath('summary.costPerKg', 2.75);
    }

    // ---- UPDATE ----

    public function test_can_update_order(): void
    {
        $deps = $this->createOrderDependencies();
        $order = $this->createOrder($deps);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/orders/'.$order->id, [
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
            ->deleteJson('/api/v2/orders/'.$order->id);

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
            ->deleteJson('/api/v2/orders/'.$order->id);

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
            ->putJson('/api/v2/orders/'.$order->id.'/status', [
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
