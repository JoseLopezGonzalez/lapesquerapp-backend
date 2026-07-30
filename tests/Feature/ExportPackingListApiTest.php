<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\CustomsBroker;
use App\Models\FishingGear;
use App\Models\Order;
use App\Models\OrderMaritimeContainer;
use App\Models\OrderMaritimeShippingDetail;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\Product;
use App\Models\Species;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class ExportPackingListApiTest extends TestCase
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

        $result = $this->createTenantAndAdminUser('export');
        $this->tenantSubdomain = $result['slug'];
        $this->token = $result['token'];

        $context = $this->createSalesContext('Export');
        $customer = $this->createCustomerForTest($context, 'Export');
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

    private function createPalletWithBox(Order $order): Pallet
    {
        $species = Species::factory()->create([
            'fishing_gear_id' => FishingGear::factory()->create()->id,
        ]);
        $product = Product::factory()->create(['species_id' => $species->id, 'hs_code' => '0307520000']);
        $pallet = Pallet::factory()->create(['order_id' => $order->id, 'status' => Pallet::STATE_SHIPPED]);
        $box = Box::factory()->create(['article_id' => $product->id, 'net_weight' => 10, 'gross_weight' => 11]);
        PalletBox::create(['pallet_id' => $pallet->id, 'box_id' => $box->id]);

        return $pallet;
    }

    public function test_can_create_and_list_customs_brokers(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/customs-brokers', [
                'name' => 'NDC Customs Brokers',
                'address' => '372 Av. Escorial, San Juan, 00920',
                'phone' => '+1 787-781-0000',
                'email' => 'ndc@ndcpr.com',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'NDC Customs Brokers');

        $listResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/customs-brokers');

        $listResponse->assertStatus(200);
        $listResponse->assertJsonCount(1, 'data');
    }

    public function test_cannot_delete_customs_broker_in_use(): void
    {
        $broker = CustomsBroker::factory()->create();
        OrderMaritimeShippingDetail::factory()->create([
            'order_id' => $this->order->id,
            'customs_broker_id' => $broker->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v2/customs-brokers/{$broker->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('customs_brokers', ['id' => $broker->id], 'tenant');
    }

    public function test_can_update_shipping_details_with_broker_and_ultimate_consignee(): void
    {
        $broker = CustomsBroker::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v2/orders/{$this->order->id}/maritime-shipping-details", [
                'vesselName' => 'Mando 631S',
                'customsBrokerId' => $broker->id,
                'ultimateConsigneeName' => 'Jose Santiago, Inc',
                'ultimateConsigneeAddress' => "P.O. BOX 191795\nSan Juan, Puerto Rico 00919-1795",
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.customsBrokerId', $broker->id);
        $response->assertJsonPath('data.customsBroker.name', $broker->name);
        $response->assertJsonPath('data.ultimateConsigneeName', 'Jose Santiago, Inc');
    }

    public function test_can_assign_single_pallet_to_container(): void
    {
        $pallet = $this->createPalletWithBox($this->order);
        $container = OrderMaritimeContainer::factory()->create(['order_id' => $this->order->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/v2/orders/{$this->order->id}/pallets/{$pallet->id}/maritime-container", [
                'containerId' => $container->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('pallets', [
            'id' => $pallet->id,
            'order_maritime_container_id' => $container->id,
        ], 'tenant');

        $unassign = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/v2/orders/{$this->order->id}/pallets/{$pallet->id}/maritime-container", [
                'containerId' => null,
            ]);

        $unassign->assertStatus(200);
        $this->assertDatabaseHas('pallets', [
            'id' => $pallet->id,
            'order_maritime_container_id' => null,
        ], 'tenant');
    }

    public function test_can_bulk_assign_and_unassign_pallets_to_container(): void
    {
        $palletA = $this->createPalletWithBox($this->order);
        $palletB = $this->createPalletWithBox($this->order);
        $container = OrderMaritimeContainer::factory()->create(['order_id' => $this->order->id]);

        $assignResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/maritime-containers/{$container->id}/pallets", [
                'palletIds' => [$palletA->id, $palletB->id],
            ]);

        $assignResponse->assertStatus(200);
        $this->assertDatabaseHas('pallets', ['id' => $palletA->id, 'order_maritime_container_id' => $container->id], 'tenant');
        $this->assertDatabaseHas('pallets', ['id' => $palletB->id, 'order_maritime_container_id' => $container->id], 'tenant');

        $listResponse = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/orders/{$this->order->id}/maritime-containers/{$container->id}/pallets");

        $listResponse->assertStatus(200);
        $listResponse->assertJsonCount(2, 'data');

        $unassignResponse = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v2/orders/{$this->order->id}/maritime-containers/{$container->id}/pallets", [
                'palletIds' => [$palletA->id],
            ]);

        $unassignResponse->assertStatus(200);
        $this->assertDatabaseHas('pallets', ['id' => $palletA->id, 'order_maritime_container_id' => null], 'tenant');
        $this->assertDatabaseHas('pallets', ['id' => $palletB->id, 'order_maritime_container_id' => $container->id], 'tenant');
    }

    public function test_bulk_assign_rejects_pallets_from_another_order(): void
    {
        $container = OrderMaritimeContainer::factory()->create(['order_id' => $this->order->id]);

        $context = $this->createSalesContext('OtherExport');
        $otherCustomer = $this->createCustomerForTest($context, 'OtherExport');
        $otherOrder = $this->createOrderForTest($otherCustomer, $context);
        $otherPallet = $this->createPalletWithBox($otherOrder);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$this->order->id}/maritime-containers/{$container->id}/pallets", [
                'palletIds' => [$otherPallet->id],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('pallets', ['id' => $otherPallet->id, 'order_maritime_container_id' => null], 'tenant');
    }

    public function test_can_generate_export_packing_list_pdf_for_container(): void
    {
        if (! file_exists('/usr/bin/google-chrome') && ! file_exists('/usr/bin/chromium')) {
            $this->markTestSkipped('Chromium not available for PDF generation');
        }

        $pallet = $this->createPalletWithBox($this->order);
        $container = OrderMaritimeContainer::factory()->create([
            'order_id' => $this->order->id,
            'container_number' => 'MNBU4374888',
            'seal_number' => 'ML-PT1199309',
        ]);
        $pallet->update(['order_maritime_container_id' => $container->id]);

        OrderMaritimeShippingDetail::factory()->create([
            'order_id' => $this->order->id,
            'vessel_name' => 'Mando 631S',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->get("/api/v2/orders/{$this->order->id}/maritime-containers/{$container->id}/pdf/export-packing-list");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    /**
     * Renderiza la plantilla Blade directamente (sin pasar por Chromium) para verificar
     * que no hay errores de variables/propiedades, útil en entornos sin binario de Chrome.
     */
    public function test_export_packing_list_view_renders_without_errors(): void
    {
        $pallet = $this->createPalletWithBox($this->order);
        $container = OrderMaritimeContainer::factory()->create([
            'order_id' => $this->order->id,
            'container_number' => 'MNBU4374888',
            'seal_number' => 'ML-PT1199309',
        ]);
        $pallet->update(['order_maritime_container_id' => $container->id]);

        $broker = CustomsBroker::factory()->create(['name' => 'NDC Customs Brokers']);
        OrderMaritimeShippingDetail::factory()->create([
            'order_id' => $this->order->id,
            'vessel_name' => 'Mando 631S',
            'customs_broker_id' => $broker->id,
            'ultimate_consignee_name' => 'Jose Santiago, Inc',
        ]);

        $order = Order::with(['maritimeShippingDetail.customsBroker', 'customer.country', 'incoterm'])->find($this->order->id);
        $container->load([
            'pallets.boxes.box.product.species.fishingGear',
            'pallets.boxes.box.product.captureZone',
        ]);

        $html = view('pdf.v2.orders.export_packing_list', ['entity' => $order, 'container' => $container])->render();

        $this->assertStringContainsString('Export Packing List', $html);
        $this->assertStringContainsString('MNBU4374888', $html);
        $this->assertStringContainsString('Mando 631S', $html);
        $this->assertStringContainsString('NDC Customs Brokers', $html);
        $this->assertStringContainsString('Jose Santiago, Inc', $html);
    }
}
