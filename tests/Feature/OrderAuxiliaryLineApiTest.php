<?php

namespace Tests\Feature;

use App\Models\AuxiliaryProduct;
use App\Models\Order;
use App\Models\OrderAuxiliaryLine;
use App\Models\Tax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderAuxiliaryLineApiTest extends TestCase
{
    use BuildsOperationsScenario;
    use ConfiguresTenantConnection;
    use RefreshDatabase;

    private ?string $token = null;

    private ?string $tenantSubdomain = null;

    private ?Order $order = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();

        $result = $this->createTenantAndAdminUser('auxline');
        $this->tenantSubdomain = $result['slug'];
        $this->token = $result['token'];

        $context = $this->createSalesContext('AuxLine');
        $customer = $this->createCustomerForTest($context, 'AuxLine');
        $this->order = $this->createOrderForTest($customer, $context);
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_create_auxiliary_line_with_catalog_product(): void
    {
        $product = AuxiliaryProduct::factory()->create(['name' => 'Nieve', 'unit' => 'kg']);
        $tax = Tax::factory()->create(['name' => 'IVA 4%', 'rate' => 4]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/auxiliary-lines", [
                'auxiliaryProductId' => $product->id,
                'quantity' => 500,
                'unit' => 'kg',
                'unitPrice' => 0.08,
                'taxId' => $tax->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.effectiveDescription', 'Nieve');
        $response->assertJsonPath('data.subtotal', 40);
        $response->assertJsonPath('data.total', 41.6);

        $this->assertDatabaseHas('order_auxiliary_lines', [
            'order_id' => $this->order->id,
            'auxiliary_product_id' => $product->id,
        ], 'tenant');
    }

    public function test_can_create_auxiliary_line_with_free_description(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/auxiliary-lines", [
                'description' => 'Servicio de gestión puntual',
                'quantity' => 1,
                'unit' => 'servicio',
                'unitPrice' => 120,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.effectiveDescription', 'Servicio de gestión puntual');
        $response->assertJsonPath('data.subtotal', 120);
    }

    public function test_create_requires_product_or_description(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/auxiliary-lines", [
                'quantity' => 5,
                'unit' => 'ud',
                'unitPrice' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }

    public function test_create_rejects_non_positive_quantity(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/auxiliary-lines", [
                'description' => 'X',
                'quantity' => 0,
                'unit' => 'ud',
                'unitPrice' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);
    }

    public function test_can_list_auxiliary_lines_of_order(): void
    {
        OrderAuxiliaryLine::factory()->count(2)->create([
            'order_id' => $this->order->id,
            'auxiliary_product_id' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/orders/{$this->order->id}/auxiliary-lines");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_can_update_auxiliary_line(): void
    {
        $line = OrderAuxiliaryLine::factory()->create([
            'order_id' => $this->order->id,
            'auxiliary_product_id' => null,
            'description' => 'Original',
            'quantity' => 10,
            'unit' => 'ud',
            'unit_price' => 2,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/v2/orders/{$this->order->id}/auxiliary-lines/{$line->id}", [
                'quantity' => 20,
                'unitPrice' => 3,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.subtotal', 60);
    }

    public function test_can_delete_auxiliary_line(): void
    {
        $line = OrderAuxiliaryLine::factory()->create([
            'order_id' => $this->order->id,
            'auxiliary_product_id' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v2/orders/{$this->order->id}/auxiliary-lines/{$line->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('order_auxiliary_lines', ['id' => $line->id], 'tenant');
    }

    public function test_cannot_touch_line_of_another_order(): void
    {
        $context = $this->createSalesContext('Other');
        $otherCustomer = $this->createCustomerForTest($context, 'Other');
        $otherOrder = $this->createOrderForTest($otherCustomer, $context);

        $line = OrderAuxiliaryLine::factory()->create([
            'order_id' => $otherOrder->id,
            'auxiliary_product_id' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v2/orders/{$this->order->id}/auxiliary-lines/{$line->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('order_auxiliary_lines', ['id' => $line->id], 'tenant');
    }

    public function test_order_detail_includes_auxiliary_lines(): void
    {
        OrderAuxiliaryLine::factory()->create([
            'order_id' => $this->order->id,
            'auxiliary_product_id' => null,
            'description' => 'Envase test',
            'quantity' => 10,
            'unit' => 'ud',
            'unit_price' => 1.5,
            'tax_id' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/orders/{$this->order->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.auxiliarySubtotal', 15);
        $response->assertJsonCount(1, 'data.auxiliaryLines');
    }
}
