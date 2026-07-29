<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderMaritimeContainer;
use App\Models\OrderMaritimeShippingDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderMaritimeShippingApiTest extends TestCase
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

        $result = $this->createTenantAndAdminUser('maritime');
        $this->tenantSubdomain = $result['slug'];
        $this->token = $result['token'];

        $context = $this->createSalesContext('Maritime');
        $customer = $this->createCustomerForTest($context, 'Maritime');
        $this->order = $this->createOrderForTest($customer, $context);
        $this->order->update(['order_type' => Order::ORDER_TYPE_MARITIME_EXPORT]);
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_order_can_be_created_as_maritime_export_type(): void
    {
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'order_type' => 'maritime_export',
        ], 'tenant');
    }

    public function test_shipping_details_not_found_when_not_set(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/orders/{$this->order->id}/maritime-shipping-details");

        $response->assertStatus(404);
    }

    public function test_can_create_shipping_details_via_upsert(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v2/orders/{$this->order->id}/maritime-shipping-details", [
                'vesselName' => 'Fjordvik',
                'voyageNumber' => 'V-2026-045',
                'exportInvoiceNumber' => 'EXP-2026-0089',
                'swbNumber' => 'SWB-778899',
                'loadingPort' => 'Vigo',
                'dischargePort' => 'Montevideo',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.vesselName', 'Fjordvik');
        $response->assertJsonPath('data.voyageNumber', 'V-2026-045');
        $response->assertJsonPath('data.exportInvoiceNumber', 'EXP-2026-0089');
        $response->assertJsonPath('data.swbNumber', 'SWB-778899');
        $response->assertJsonPath('data.loadingPort', 'Vigo');
        $response->assertJsonPath('data.dischargePort', 'Montevideo');

        $this->assertDatabaseHas('order_maritime_shipping_details', [
            'order_id' => $this->order->id,
            'vessel_name' => 'Fjordvik',
        ], 'tenant');
    }

    public function test_updating_shipping_details_replaces_existing_record_not_duplicates(): void
    {
        OrderMaritimeShippingDetail::factory()->create([
            'order_id' => $this->order->id,
            'vessel_name' => 'Old Vessel',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v2/orders/{$this->order->id}/maritime-shipping-details", [
                'vesselName' => 'New Vessel',
                'voyageNumber' => 'V-2026-999',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.vesselName', 'New Vessel');

        $this->assertDatabaseCount('order_maritime_shipping_details', 1, 'tenant');
    }

    public function test_can_list_and_create_containers(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/maritime-containers", [
                'containerNumber' => 'MSKU1234567',
                'sealNumber' => 'SL987654',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.containerNumber', 'MSKU1234567');
        $response->assertJsonPath('data.sealNumber', 'SL987654');

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/maritime-containers", [
                'containerNumber' => 'MSKU7654321',
                'sealNumber' => 'SL123456',
            ]);

        $listResponse = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/orders/{$this->order->id}/maritime-containers");

        $listResponse->assertStatus(200);
        $listResponse->assertJsonCount(2, 'data');
    }

    public function test_container_requires_container_number(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/maritime-containers", [
                'sealNumber' => 'SL123456',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['containerNumber']);
    }

    public function test_can_update_container(): void
    {
        $container = OrderMaritimeContainer::factory()->create([
            'order_id' => $this->order->id,
            'container_number' => 'MSKU0000001',
            'seal_number' => 'SL000001',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/v2/orders/{$this->order->id}/maritime-containers/{$container->id}", [
                'sealNumber' => 'SL999999',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.containerNumber', 'MSKU0000001');
        $response->assertJsonPath('data.sealNumber', 'SL999999');
    }

    public function test_can_delete_container(): void
    {
        $container = OrderMaritimeContainer::factory()->create([
            'order_id' => $this->order->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v2/orders/{$this->order->id}/maritime-containers/{$container->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('order_maritime_containers', ['id' => $container->id], 'tenant');
    }

    public function test_cannot_touch_container_of_another_order(): void
    {
        $context = $this->createSalesContext('OtherMaritime');
        $otherCustomer = $this->createCustomerForTest($context, 'OtherMaritime');
        $otherOrder = $this->createOrderForTest($otherCustomer, $context);

        $container = OrderMaritimeContainer::factory()->create([
            'order_id' => $otherOrder->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v2/orders/{$this->order->id}/maritime-containers/{$container->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('order_maritime_containers', ['id' => $container->id], 'tenant');
    }

    public function test_order_detail_includes_maritime_shipping_data(): void
    {
        OrderMaritimeShippingDetail::factory()->create([
            'order_id' => $this->order->id,
            'vessel_name' => 'Fjordvik',
        ]);
        OrderMaritimeContainer::factory()->create([
            'order_id' => $this->order->id,
            'container_number' => 'MSKU1234567',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/orders/{$this->order->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.maritimeShippingDetail.vesselName', 'Fjordvik');
        $response->assertJsonCount(1, 'data.maritimeContainers');
    }
}
