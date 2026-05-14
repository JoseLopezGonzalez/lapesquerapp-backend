<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\CaptureZone;
use App\Models\FishingGear;
use App\Models\Incident;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\Process;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionCost;
use App\Models\ProductionInput;
use App\Models\ProductionOutput;
use App\Models\ProductionOutputConsumption;
use App\Models\ProductionOutputSource;
use App\Models\ProductionRecord;
use App\Models\RawMaterialReception;
use App\Models\Species;
use App\Models\Store;
use App\Models\StoredPallet;
use App\Models\Supplier;
use App\Models\Tax;
use App\Services\Production\ProductionInputService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\BuildsProductionScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for A.6 Production block: productions index, require auth.
 * Production is complex; this file covers flujos básicos de listado y auth.
 */
class ProductionBlockApiTest extends TestCase
{
    use BuildsOperationsScenario;
    use BuildsProductionScenario;
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
        $slug = 'production-'.uniqid();
        $scenario = $this->createProductionScenario($slug);

        $this->token = $scenario['user']->createToken('test')->plainTextToken;
        $this->tenantSubdomain = $scenario['slug'];
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_productions_list_returns_paginated(): void
    {
        $gear = FishingGear::create(['name' => 'Arrastre']);
        Species::create([
            'name' => 'Merluza',
            'scientific_name' => 'Merluccius',
            'fao' => 'HKE',
            'image' => 'https://example.com/species.png',
            'fishing_gear_id' => $gear->id,
        ]);
        CaptureZone::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions');

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_productions_list_ordered_by_earliest_root_record_started_at(): void
    {
        $scenario = $this->createProductionScenario('prod-order-'.uniqid());
        $headers = [
            'X-Tenant' => $scenario['slug'],
            'Authorization' => 'Bearer '.$scenario['user']->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ];

        $prodA = $scenario['production'];
        $parentA = $scenario['parentRecord'];

        $prodA->update(['opened_at' => '2026-06-01 10:00:00']);
        $parentA->update(['started_at' => '2026-06-01 08:00:00']);

        $process = Process::create(['name' => 'Otro proceso '.uniqid(), 'type' => 'process']);
        $prodB = Production::create([
            'lot' => 'LOT-B-'.uniqid(),
            'date' => '2026-06-01',
            'species_id' => $scenario['species']->id,
            'capture_zone_id' => $scenario['captureZone']->id,
            'opened_at' => '2026-05-01 10:00:00',
        ]);
        ProductionRecord::create([
            'production_id' => $prodB->id,
            'process_id' => $process->id,
            'parent_record_id' => null,
            'started_at' => '2026-06-15 12:00:00',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/v2/productions');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $posA = array_search($prodA->id, $ids, true);
        $posB = array_search($prodB->id, $ids, true);
        $this->assertIsInt($posA);
        $this->assertIsInt($posB);
        $this->assertLessThan($posA, $posB, 'La producción con started_at de raíz más reciente debe ir antes (orden DESC).');
    }

    public function test_productions_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/productions');

        $response->assertUnauthorized();
    }

    public function test_closure_check_returns_human_readable_blocking_reasons(): void
    {
        $production = Production::query()->with(['species', 'captureZone'])->firstOrFail();
        $product = $this->createValidProduct($production->species, $production->captureZone, 'HRM');
        $order = Order::factory()->incident()->create();
        Incident::factory()->open()->create(['order_id' => $order->id]);
        $pallet = Pallet::factory()->create([
            'status' => Pallet::STATE_REGISTERED,
            'order_id' => $order->id,
        ]);
        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => $production->lot,
        ]);

        PalletBox::create([
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/closure-check');

        $response->assertOk();

        $reasons = collect($response->json('data.blockingReasons'));
        $processReason = $reasons->firstWhere('code', 'process_not_finished');
        $orderReason = $reasons->firstWhere('code', 'pending_order');

        $this->assertNotNull($processReason);
        $this->assertSame('Proceso sin finalizar', $processReason['label']);
        $this->assertStringContainsString($processReason['processName'], $processReason['message']);
        $this->assertStringNotContainsString('proceso ID', $processReason['message']);
        $this->assertSame($processReason['entityLabel'], $processReason['processName'].' (proceso #'.$processReason['recordId'].')');

        $this->assertNotNull($orderReason);
        $this->assertSame('Pedido no cerrado', $orderReason['label']);
        $this->assertSame('con incidencia abierta', $orderReason['orderStatusLabel']);
        $this->assertStringContainsString('con incidencia abierta', $orderReason['message']);
        $this->assertStringNotContainsString("'incident'", $orderReason['message']);
    }

    public function test_closure_check_does_not_block_resolved_incident_orders(): void
    {
        $production = Production::query()->with(['species', 'captureZone'])->firstOrFail();
        $product = $this->createValidProduct($production->species, $production->captureZone, 'RSI');
        $order = Order::factory()->incident()->create();
        Incident::factory()->resolved()->create(['order_id' => $order->id]);
        $pallet = Pallet::factory()->create([
            'status' => Pallet::STATE_SHIPPED,
            'order_id' => $order->id,
        ]);
        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => $production->lot,
        ]);

        PalletBox::create([
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/closure-check');

        $response->assertOk();

        $reasons = collect($response->json('data.blockingReasons'));

        $this->assertNull($reasons->firstWhere('code', 'pending_order'));
        $this->assertNull($reasons->firstWhere('code', 'pallet_not_shipped'));
    }

    public function test_box_creation_is_blocked_by_closed_lot_even_for_different_species(): void
    {
        $production = Production::query()->with('captureZone')->firstOrFail();
        $production->forceFill(['closed_at' => now('UTC')])->saveQuietly();

        $gear = FishingGear::create(['name' => 'Volanta '.uniqid()]);
        $otherSpecies = Species::create([
            'name' => 'Rape '.uniqid(),
            'scientific_name' => 'Lophius '.uniqid(),
            'fao' => 'MON',
            'image' => 'https://example.com/species-monkfish.png',
            'fishing_gear_id' => $gear->id,
        ]);
        $otherSpeciesProduct = $this->createValidProduct($otherSpecies, $production->captureZone, 'CLS');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("el lote '{$production->lot}' pertenece a una producción cerrada definitivamente");

        Box::create([
            'article_id' => $otherSpeciesProduct->id,
            'lot' => $production->lot,
            'gs1_128' => 'CLS-'.$production->id,
            'gross_weight' => 11,
            'net_weight' => 10,
        ]);
    }

    public function test_existing_boxes_from_closed_lots_cannot_be_modified_or_deleted(): void
    {
        $production = Production::query()->with(['species', 'captureZone'])->firstOrFail();
        $product = $this->createValidProduct($production->species, $production->captureZone, 'CLB');
        $box = Box::create([
            'article_id' => $product->id,
            'lot' => $production->lot,
            'gs1_128' => 'CLB-'.$production->id,
            'gross_weight' => 11,
            'net_weight' => 10,
        ]);

        $production->forceFill(['closed_at' => now('UTC')])->saveQuietly();

        try {
            $box->update(['net_weight' => 9]);
            $this->fail('Se permitió editar una caja de un lote cerrado.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('pertenece a una producción cerrada definitivamente', $e->getMessage());
        }

        $box->refresh();

        try {
            $box->delete();
            $this->fail('Se permitió eliminar una caja de un lote cerrado.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('pertenece a una producción cerrada definitivamente', $e->getMessage());
        }
    }

    public function test_closed_lot_boxes_cannot_be_consumed_by_another_open_production(): void
    {
        $sourceProduction = Production::query()->with(['species', 'captureZone'])->firstOrFail();
        $product = $this->createValidProduct($sourceProduction->species, $sourceProduction->captureZone, 'CLI');
        $box = Box::create([
            'article_id' => $product->id,
            'lot' => $sourceProduction->lot,
            'gs1_128' => 'CLI-'.$sourceProduction->id,
            'gross_weight' => 11,
            'net_weight' => 10,
        ]);
        $sourceProduction->forceFill(['closed_at' => now('UTC')])->saveQuietly();

        $targetProduction = Production::create([
            'lot' => 'OPEN-'.uniqid(),
            'date' => now()->toDateString(),
            'species_id' => $sourceProduction->species_id,
            'capture_zone_id' => $sourceProduction->capture_zone_id,
            'opened_at' => now('UTC'),
        ]);
        $targetRecord = ProductionRecord::create([
            'production_id' => $targetProduction->id,
            'process_id' => Process::query()->value('id'),
            'started_at' => now('UTC'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pertenece a una producción cerrada definitivamente');

        ProductionInput::create([
            'production_record_id' => $targetRecord->id,
            'box_id' => $box->id,
        ]);
    }

    public function test_production_store_rejects_duplicate_lot(): void
    {
        $gear = FishingGear::create(['name' => 'Arrastre '.uniqid()]);
        $species = Species::create([
            'name' => 'Merluza '.uniqid(),
            'scientific_name' => 'Merluccius',
            'fao' => 'HKE',
            'image' => 'https://example.com/species.png',
            'fishing_gear_id' => $gear->id,
        ]);
        $captureZone = CaptureZone::factory()->create();
        $lot = 'LOT-UNIQUE-'.uniqid();

        $payload = [
            'lot' => $lot,
            'species_id' => $species->id,
            'capture_zone_id' => $captureZone->id,
            'notes' => 'Lote inicial',
        ];

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/productions', $payload)
            ->assertCreated();

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/productions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lot']);
    }

    public function test_production_update_rejects_duplicate_lot(): void
    {
        $gear = FishingGear::create(['name' => 'Cerco '.uniqid()]);
        $species = Species::create([
            'name' => 'Atun '.uniqid(),
            'scientific_name' => 'Thunnus',
            'fao' => 'TUN',
            'image' => 'https://example.com/species.png',
            'fishing_gear_id' => $gear->id,
        ]);
        $captureZone = CaptureZone::factory()->create();

        $productionA = Production::create([
            'lot' => 'LOT-A-'.uniqid(),
            'date' => '2026-06-01',
            'species_id' => $species->id,
            'capture_zone_id' => $captureZone->id,
        ]);

        $productionB = Production::create([
            'lot' => 'LOT-B-'.uniqid(),
            'date' => '2026-06-01',
            'species_id' => $species->id,
            'capture_zone_id' => $captureZone->id,
        ]);

        $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/productions/'.$productionB->id, [
                'lot' => $productionA->lot,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lot']);
    }

    private function createProductionWithRecords(): array
    {
        $scenario = $this->createProductionScenario('production-graph-'.uniqid());

        return [$scenario['production'], $scenario['parentRecord'], $scenario['childRecord']];
    }

    private function createValidProduct(Species $species, CaptureZone $captureZone, string $suffix = '01'): Product
    {
        return Product::create([
            'name' => 'Producto '.$suffix,
            'species_id' => $species->id,
            'capture_zone_id' => $captureZone->id,
            'article_gtin' => 'A'.str_pad($suffix, 12, '1'),
            'box_gtin' => 'B'.str_pad($suffix, 12, '2'),
            'pallet_gtin' => 'P'.str_pad($suffix, 12, '3'),
            'fixed_weight' => 1.50,
            'facil_com_code' => 'FC-'.$suffix,
        ]);
    }

    private function createShippedPalletWithBox(Order $order, Product $product, string $lot, float $netWeight): Pallet
    {
        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => $lot,
            'net_weight' => $netWeight,
            'gross_weight' => $netWeight + 1,
        ]);

        $pallet = Pallet::factory()->shipped()->create([
            'order_id' => $order->id,
        ]);

        PalletBox::create([
            'box_id' => $box->id,
            'pallet_id' => $pallet->id,
        ]);

        return $pallet;
    }

    private function createStoredPalletWithBox(Product $product, string $lot, float $netWeight): Pallet
    {
        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => $lot,
            'net_weight' => $netWeight,
            'gross_weight' => $netWeight + 1,
        ]);

        $pallet = Pallet::factory()->stored()->create();

        PalletBox::create([
            'box_id' => $box->id,
            'pallet_id' => $pallet->id,
        ]);

        return $pallet;
    }

    public function test_production_record_show_returns_parent_with_process(): void
    {
        [, $parentRecord, $childRecord] = $this->createProductionWithRecords();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/production-records/'.$childRecord->id);

        $response->assertOk()
            ->assertJsonPath('data.parent.id', $parentRecord->id)
            ->assertJsonPath('data.parent.process.id', $parentRecord->process_id)
            ->assertJsonPath('data.parent.process.name', 'Corte');
    }

    public function test_production_process_tree_stock_inputs_include_cost_metrics(): void
    {
        [$production, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $sourceProduct = $this->createValidProduct($species, $captureZone, '10I');

        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $sourceProduct->id,
            'lot_id' => 'L-INPUT-COST',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);

        ProductionCost::create([
            'production_record_id' => $childRecord->id,
            'production_id' => null,
            'cost_type' => ProductionCost::COST_TYPE_PRODUCTION,
            'name' => 'Proceso final input',
            'total_cost' => 50,
            'cost_per_kg' => null,
            'distribution_unit' => 'total',
            'cost_date' => now()->toDateString(),
        ]);

        $box = Box::factory()->create([
            'article_id' => $sourceProduct->id,
            'lot' => $production->lot,
            'net_weight' => 20,
            'gross_weight' => 21,
        ]);

        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $box->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/process-tree');

        $response->assertOk()
            ->assertJsonPath('data.processNodes.0.inputs.0.type', 'stock_box')
            ->assertJsonPath('data.processNodes.0.inputs.0.weight', '20.000')
            ->assertJsonPath('data.processNodes.0.inputs.0.costPerKg', 0.5)
            ->assertJsonPath('data.processNodes.0.inputs.0.totalCost', 10);
    }

    public function test_production_process_tree_reuses_cost_for_multiple_stock_inputs_from_same_lot_and_product(): void
    {
        [$production, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $sourceProduct = $this->createValidProduct($species, $captureZone, '10R');

        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $sourceProduct->id,
            'lot_id' => 'L-REUSED-COST',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);

        ProductionCost::create([
            'production_record_id' => $childRecord->id,
            'production_id' => null,
            'cost_type' => ProductionCost::COST_TYPE_PRODUCTION,
            'name' => 'Proceso final reused',
            'total_cost' => 50,
            'cost_per_kg' => null,
            'distribution_unit' => 'total',
            'cost_date' => now()->toDateString(),
        ]);

        $boxA = Box::factory()->create([
            'article_id' => $sourceProduct->id,
            'lot' => $production->lot,
            'net_weight' => 20,
            'gross_weight' => 21,
        ]);
        $boxB = Box::factory()->create([
            'article_id' => $sourceProduct->id,
            'lot' => $production->lot,
            'net_weight' => 30,
            'gross_weight' => 31,
        ]);

        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $boxA->id,
        ]);
        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $boxB->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/process-tree');

        $response->assertOk()
            ->assertJsonPath('data.processNodes.0.inputs.0.costPerKg', 0.5)
            ->assertJsonPath('data.processNodes.0.inputs.1.costPerKg', 0.5)
            ->assertJsonPath('data.processNodes.0.inputs.0.totalCost', 10)
            ->assertJsonPath('data.processNodes.0.inputs.1.totalCost', 15);
    }

    public function test_production_record_tree_includes_output_and_node_accounting_costs(): void
    {
        [, $parentRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '10C');

        $output = ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-TREE-COST',
            'boxes' => 8,
            'weight_kg' => 80,
        ]);

        ProductionCost::create([
            'production_record_id' => $parentRecord->id,
            'production_id' => null,
            'cost_type' => ProductionCost::COST_TYPE_LABOR,
            'name' => 'Mano de obra corte',
            'total_cost' => 40,
            'cost_per_kg' => null,
            'distribution_unit' => 'total',
            'cost_date' => now()->toDateString(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/production-records/'.$parentRecord->id.'/tree');

        $response->assertOk()
            ->assertJsonPath('data.outputs.0.id', $output->id)
            ->assertJsonPath('data.outputs.0.totalCost', 40)
            ->assertJsonPath('data.outputs.0.costPerKg', 0.5)
            ->assertJsonPath('data.outputs.0.costBreakdown.process_costs.labor.total_cost', 40)
            ->assertJsonPath('data.costs.totalCost', 40)
            ->assertJsonPath('data.costs.averageCostPerKg', 0.5)
            ->assertJsonPath('data.costs.processCost', 40)
            ->assertJsonPath('data.costs.processLaborCost', 40)
            ->assertJsonPath('data.costs.materialsCost', 0)
            ->assertJsonPath('data.costs.productionCost', 0);
    }

    public function test_production_process_tree_sales_node_includes_sale_price_and_cost_metrics(): void
    {
        [$production, , $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '10S');

        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-SALES-TREE',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);

        ProductionCost::create([
            'production_record_id' => $childRecord->id,
            'production_id' => null,
            'cost_type' => ProductionCost::COST_TYPE_PRODUCTION,
            'name' => 'Proceso final',
            'total_cost' => 50,
            'cost_per_kg' => null,
            'distribution_unit' => 'total',
            'cost_date' => now()->toDateString(),
        ]);

        $salesContext = $this->createSalesContext('PTREE');
        $customer = $this->createCustomerForTest($salesContext, 'PTREE');
        $order = $this->createOrderForTest($customer, $salesContext);
        $tax = Tax::query()->firstOrCreate(
            ['name' => 'IVA 10'],
            ['name' => 'IVA 10', 'rate' => 10]
        );

        OrderPlannedProductDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'tax_id' => $tax->id,
            'quantity' => 20,
            'boxes' => 2,
            'unit_price' => 4,
        ]);

        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => $production->lot,
            'net_weight' => 20,
            'gross_weight' => 21,
        ]);

        $pallet = Pallet::factory()->shipped()->create([
            'order_id' => $order->id,
        ]);

        PalletBox::create([
            'box_id' => $box->id,
            'pallet_id' => $pallet->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/process-tree');

        $response->assertOk()
            ->assertJsonPath('data.processNodes.0.children.0.children.0.type', 'sales')
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.products.0.salePricePerKg', 4)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.products.0.saleSubtotal', 80)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.products.0.saleTotal', 88)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.products.0.costPerKg', 0.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.products.0.costTotal', 10)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.products.0.marginTotalExTax', 70)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.products.0.marginPerKgExTax', 3.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.products.0.marginPercentageExTax', 87.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.salePricePerKg', 4)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.saleSubtotal', 80)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.saleTotal', 88)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.costPerKg', 0.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.costTotal', 10)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.marginTotalExTax', 70)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.marginPerKgExTax', 3.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.marginPercentageExTax', 87.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.summary.salePricePerKg', 4)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.summary.saleSubtotal', 80)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.summary.saleTotal', 88)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.summary.costPerKg', 0.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.summary.costTotal', 10)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.summary.marginTotalExTax', 70)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.summary.marginPerKgExTax', 3.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.summary.marginPercentageExTax', 87.5);
    }

    public function test_filtered_process_tree_by_customer_only_includes_expedited_sales_for_that_customer(): void
    {
        [$production, , $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '10FC');
        $otherProduct = $this->createValidProduct($species, $captureZone, '10FO');

        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-FILTERED-CUSTOMER',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);
        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $otherProduct->id,
            'lot_id' => 'L-FILTERED-OTHER',
            'boxes' => 5,
            'weight_kg' => 50,
        ]);

        $salesContext = $this->createSalesContext('PFILTER');
        $customer = $this->createCustomerForTest($salesContext, 'PFILTER');
        $otherCustomer = $this->createCustomerForTest($salesContext, 'PFILTER-OTHER');
        $order = $this->createOrderForTest($customer, $salesContext);
        $order->update(['status' => Order::STATUS_FINISHED]);
        $otherOrder = $this->createOrderForTest($otherCustomer, $salesContext);
        $otherOrder->update(['status' => Order::STATUS_FINISHED]);
        $tax = Tax::query()->firstOrCreate(
            ['name' => 'IVA Filtered'],
            ['name' => 'IVA Filtered', 'rate' => 10]
        );

        OrderPlannedProductDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'tax_id' => $tax->id,
            'quantity' => 25,
            'boxes' => 1,
            'unit_price' => 4,
        ]);
        OrderPlannedProductDetail::create([
            'order_id' => $otherOrder->id,
            'product_id' => $otherProduct->id,
            'tax_id' => $tax->id,
            'quantity' => 30,
            'boxes' => 1,
            'unit_price' => 5,
        ]);

        $this->createShippedPalletWithBox($order, $product, $production->lot, 25);
        $this->createShippedPalletWithBox($otherOrder, $otherProduct, $production->lot, 30);
        $this->createStoredPalletWithBox($product, $production->lot, 45);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/process-tree?customerId='.$customer->id);

        $response->assertOk()
            ->assertJsonPath('data.processNodes.0.children.0.outputs.0.productId', $product->id)
            ->assertJsonPath('data.processNodes.0.children.0.outputs.0.weightKg', 25)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.type', 'sales')
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.order.id', $order->id)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.products.0.totalNetWeight', 25)
            ->assertJsonPath('data.totals.totalSalesWeight', 25);

        $types = collect($response->json('data.processNodes.0.children.0.children'))->pluck('type')->all();
        $this->assertNotContains('stock', $types);
        $this->assertNotContains('reprocessed', $types);
        $this->assertNotContains('balance', $types);
    }

    public function test_filtered_process_tree_by_order_accepts_incident_orders_when_pallets_are_shipped(): void
    {
        [$production, , $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '10FI');

        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-FILTERED-INCIDENT',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);

        $salesContext = $this->createSalesContext('PINC');
        $customer = $this->createCustomerForTest($salesContext, 'PINC');
        $order = $this->createOrderForTest($customer, $salesContext);
        $order->update(['status' => Order::STATUS_INCIDENT]);
        $tax = Tax::query()->firstOrCreate(
            ['name' => 'IVA Incident'],
            ['name' => 'IVA Incident', 'rate' => 10]
        );
        OrderPlannedProductDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'tax_id' => $tax->id,
            'quantity' => 20,
            'boxes' => 1,
            'unit_price' => 4,
        ]);
        $this->createShippedPalletWithBox($order, $product, $production->lot, 20);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/process-tree?orderId='.$order->id);

        $response->assertOk()
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.order.status', Order::STATUS_INCIDENT)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.orders.0.products.0.totalNetWeight', 20);
    }

    public function test_filtered_process_tree_rejects_customer_and_order_together(): void
    {
        [$production] = $this->createProductionWithRecords();
        $salesContext = $this->createSalesContext('PBOTH');
        $customer = $this->createCustomerForTest($salesContext, 'PBOTH');
        $order = $this->createOrderForTest($customer, $salesContext);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/process-tree?customerId='.$customer->id.'&orderId='.$order->id);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customerId']);
    }

    public function test_filtered_process_tree_allocates_same_product_across_final_nodes_proportionally(): void
    {
        [$production, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '10FP');
        $secondProcess = Process::create(['name' => 'Segundo final '.uniqid(), 'type' => 'final']);
        $secondFinalRecord = ProductionRecord::create([
            'production_id' => $production->id,
            'parent_record_id' => $parentRecord->id,
            'process_id' => $secondProcess->id,
            'started_at' => now(),
        ]);

        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-FILTERED-PROP-A',
            'boxes' => 6,
            'weight_kg' => 60,
        ]);
        ProductionOutput::create([
            'production_record_id' => $secondFinalRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-FILTERED-PROP-B',
            'boxes' => 4,
            'weight_kg' => 40,
        ]);

        $salesContext = $this->createSalesContext('PPROP');
        $customer = $this->createCustomerForTest($salesContext, 'PPROP');
        $order = $this->createOrderForTest($customer, $salesContext);
        $order->update(['status' => Order::STATUS_FINISHED]);
        $tax = Tax::query()->firstOrCreate(
            ['name' => 'IVA Prop'],
            ['name' => 'IVA Prop', 'rate' => 10]
        );
        OrderPlannedProductDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'tax_id' => $tax->id,
            'quantity' => 25,
            'boxes' => 1,
            'unit_price' => 4,
        ]);
        $this->createShippedPalletWithBox($order, $product, $production->lot, 25);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/process-tree?orderId='.$order->id);

        $response->assertOk();

        $children = collect($response->json('data.processNodes.0.children'));
        $weights = $children
            ->map(fn ($node) => $node['outputs'][0]['weightKg'] ?? null)
            ->filter()
            ->sort()
            ->values()
            ->all();

        $this->assertEquals([10, 15], $weights);
        $this->assertEquals(25, $response->json('data.totals.totalOutputWeight'));
    }

    public function test_filtered_process_tree_traces_sources_and_removes_unrelated_parent_data(): void
    {
        [$production, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $rawA = $this->createValidProduct($species, $captureZone, '10RA');
        $rawB = $this->createValidProduct($species, $captureZone, '10RB');
        $finalA = $this->createValidProduct($species, $captureZone, '10FA');
        $finalB = $this->createValidProduct($species, $captureZone, '10FB');

        $rawBoxA = Box::factory()->create([
            'article_id' => $rawA->id,
            'lot' => 'RAW-A',
            'net_weight' => 100,
            'gross_weight' => 101,
        ]);
        $rawBoxB = Box::factory()->create([
            'article_id' => $rawB->id,
            'lot' => 'RAW-B',
            'net_weight' => 50,
            'gross_weight' => 51,
        ]);
        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $rawBoxA->id,
        ]);
        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $rawBoxB->id,
        ]);

        $parentOutputA = ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $rawA->id,
            'lot_id' => 'PARENT-A',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);
        $parentOutputB = ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $rawB->id,
            'lot_id' => 'PARENT-B',
            'boxes' => 5,
            'weight_kg' => 50,
        ]);
        ProductionOutputSource::create([
            'production_output_id' => $parentOutputA->id,
            'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT,
            'product_id' => $rawA->id,
            'contributed_weight_kg' => 100,
        ]);
        ProductionOutputSource::create([
            'production_output_id' => $parentOutputB->id,
            'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT,
            'product_id' => $rawB->id,
            'contributed_weight_kg' => 50,
        ]);

        $consumptionA = ProductionOutputConsumption::create([
            'production_record_id' => $childRecord->id,
            'production_output_id' => $parentOutputA->id,
            'consumed_weight_kg' => 100,
            'consumed_boxes' => 10,
        ]);
        $consumptionB = ProductionOutputConsumption::create([
            'production_record_id' => $childRecord->id,
            'production_output_id' => $parentOutputB->id,
            'consumed_weight_kg' => 50,
            'consumed_boxes' => 5,
        ]);

        $finalOutputA = ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $finalA->id,
            'lot_id' => 'FINAL-A',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);
        $finalOutputB = ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $finalB->id,
            'lot_id' => 'FINAL-B',
            'boxes' => 5,
            'weight_kg' => 50,
        ]);
        ProductionOutputSource::create([
            'production_output_id' => $finalOutputA->id,
            'source_type' => ProductionOutputSource::SOURCE_TYPE_PARENT_OUTPUT,
            'production_output_consumption_id' => $consumptionA->id,
            'contributed_weight_kg' => 100,
        ]);
        ProductionOutputSource::create([
            'production_output_id' => $finalOutputB->id,
            'source_type' => ProductionOutputSource::SOURCE_TYPE_PARENT_OUTPUT,
            'production_output_consumption_id' => $consumptionB->id,
            'contributed_weight_kg' => 50,
        ]);

        $salesContext = $this->createSalesContext('PTRACE');
        $customer = $this->createCustomerForTest($salesContext, 'PTRACE');
        $order = $this->createOrderForTest($customer, $salesContext);
        $order->update(['status' => Order::STATUS_FINISHED]);
        $tax = Tax::query()->firstOrCreate(
            ['name' => 'IVA Trace'],
            ['name' => 'IVA Trace', 'rate' => 10]
        );
        OrderPlannedProductDetail::create([
            'order_id' => $order->id,
            'product_id' => $finalA->id,
            'tax_id' => $tax->id,
            'quantity' => 20,
            'boxes' => 1,
            'unit_price' => 4,
        ]);
        $this->createShippedPalletWithBox($order, $finalA, $production->lot, 20);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/process-tree?orderId='.$order->id);

        $response->assertOk();
        $root = $response->json('data.processNodes.0');
        $child = $root['children'][0];

        $this->assertSame([$rawA->id], collect($root['inputs'])->pluck('product.id')->unique()->values()->all());
        $this->assertSame([$rawA->id], collect($root['outputs'])->pluck('productId')->values()->all());
        $this->assertSame([$parentOutputA->id], collect($child['parentOutputConsumptions'])->pluck('productionOutputId')->values()->all());
        $this->assertSame([$finalA->id], collect($child['outputs'])->pluck('productId')->values()->all());
    }

    public function test_production_process_tree_stock_node_includes_cost_metrics(): void
    {
        [$production, , $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '10T');

        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-STOCK-TREE',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);

        ProductionCost::create([
            'production_record_id' => $childRecord->id,
            'production_id' => null,
            'cost_type' => ProductionCost::COST_TYPE_PRODUCTION,
            'name' => 'Proceso final stock',
            'total_cost' => 50,
            'cost_per_kg' => null,
            'distribution_unit' => 'total',
            'cost_date' => now()->toDateString(),
        ]);

        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => $production->lot,
            'net_weight' => 20,
            'gross_weight' => 21,
        ]);

        $pallet = Pallet::factory()->stored()->create([
            'order_id' => null,
        ]);

        PalletBox::create([
            'box_id' => $box->id,
            'pallet_id' => $pallet->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/process-tree');

        $response->assertOk()
            ->assertJsonPath('data.processNodes.0.children.0.children.0.type', 'stock')
            ->assertJsonPath('data.processNodes.0.children.0.children.0.stores.0.products.0.costPerKg', 0.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.stores.0.products.0.costTotal', 10)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.stores.0.costPerKg', 0.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.stores.0.costTotal', 10)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.summary.costPerKg', 0.5)
            ->assertJsonPath('data.processNodes.0.children.0.children.0.summary.costTotal', 10);
    }

    public function test_production_process_tree_reprocessed_node_includes_cost_metrics(): void
    {
        [$production, , $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '10RP');

        $output = ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-REPROCESSED-TREE',
            'boxes' => 2,
            'weight_kg' => 40,
        ]);

        ProductionCost::create([
            'production_record_id' => $childRecord->id,
            'production_id' => null,
            'cost_type' => ProductionCost::COST_TYPE_PRODUCTION,
            'name' => 'Proceso final reprocesado',
            'total_cost' => 20,
            'cost_per_kg' => null,
            'distribution_unit' => 'total',
            'cost_date' => now()->toDateString(),
        ]);

        $boxA = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => $production->lot,
            'net_weight' => 10,
            'gross_weight' => 10.4,
        ]);
        $boxB = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => $production->lot,
            'net_weight' => 12,
            'gross_weight' => 12.5,
        ]);

        $reprocessedProduction = Production::create([
            'lot' => 'LOT-REPROCESSED-'.uniqid(),
            'date' => now()->toDateString(),
            'species_id' => $species->id,
            'capture_zone_id' => $captureZone->id,
            'opened_at' => now(),
        ]);
        $reprocessedProcess = Process::create([
            'name' => 'Reprocesado test '.uniqid(),
            'type' => 'process',
        ]);
        $reprocessedRecord = ProductionRecord::create([
            'production_id' => $reprocessedProduction->id,
            'process_id' => $reprocessedProcess->id,
            'started_at' => now(),
        ]);

        ProductionInput::create([
            'production_record_id' => $reprocessedRecord->id,
            'box_id' => $boxA->id,
        ]);
        ProductionInput::create([
            'production_record_id' => $reprocessedRecord->id,
            'box_id' => $boxB->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/process-tree');

        $response->assertOk();

        $reprocessedNode = collect($response->json('data.processNodes.0.children.0.children'))
            ->firstWhere('type', 'reprocessed');

        $this->assertNotNull($reprocessedNode);
        $this->assertSame(1, $reprocessedNode['summary']['processesCount']);
        $this->assertSame(1, $reprocessedNode['summary']['productsCount']);
        $this->assertEquals(22.0, $reprocessedNode['summary']['netWeight']);
        $this->assertEquals(0.5, $reprocessedNode['summary']['costPerKg']);
        $this->assertEquals(11.0, $reprocessedNode['summary']['costTotal']);
        $this->assertEquals(0.5, $reprocessedNode['processes'][0]['products'][0]['costPerKg']);
        $this->assertEquals(11.0, $reprocessedNode['processes'][0]['products'][0]['costTotal']);
        $this->assertEquals(0.5, $reprocessedNode['processes'][0]['costPerKg']);
        $this->assertEquals(11.0, $reprocessedNode['processes'][0]['costTotal']);
    }

    public function test_available_outputs_keeps_current_record_consumption_when_fully_consumed(): void
    {
        [, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '11');

        $output = ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L1',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);

        ProductionOutputConsumption::create([
            'production_record_id' => $childRecord->id,
            'production_output_id' => $output->id,
            'consumed_weight_kg' => 100,
            'consumed_boxes' => 10,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/production-output-consumptions/available-outputs/'.$childRecord->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.output.id', $output->id)
            ->assertJsonPath('data.0.hasExistingConsumption', true);
    }

    public function test_production_records_root_only_filter_behaves_correctly(): void
    {
        [$production, $parentRecord, $childRecord] = $this->createProductionWithRecords();

        $responseRootTrue = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/production-records?production_id='.$production->id.'&root_only=1');

        $responseRootFalse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/production-records?production_id='.$production->id.'&root_only=0');

        $responseRootTrue->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $parentRecord->id);

        $responseRootFalse->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($responseRootFalse->json('data'))->pluck('id')->all();
        $this->assertContains($parentRecord->id, $ids);
        $this->assertContains($childRecord->id, $ids);
    }

    public function test_sync_consumptions_rejects_zero_consumed_weight(): void
    {
        [, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '22');

        $output = ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L1',
            'boxes' => 5,
            'weight_kg' => 50,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/production-records/'.$childRecord->id.'/parent-output-consumptions', [
                'consumptions' => [
                    [
                        'production_output_id' => $output->id,
                        'consumed_weight_kg' => 0,
                        'consumed_boxes' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['consumptions.0.consumed_weight_kg']);
    }

    public function test_production_show_uses_final_outputs_for_total_output_weight(): void
    {
        [$production, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $parentProduct = $this->createValidProduct($species, $captureZone, '31');
        $finalProduct = $this->createValidProduct($species, $captureZone, '32');

        // Output intermedio del nodo padre (no debe contar en total global).
        ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $parentProduct->id,
            'lot_id' => 'L-INTERMEDIATE',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);

        // Output final del nodo hijo (sí debe contar en total global).
        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $finalProduct->id,
            'lot_id' => 'L-FINAL',
            'boxes' => 4,
            'weight_kg' => 40,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id);

        $response->assertOk()
            ->assertJsonPath('data.totalOutputWeight', 40)
            ->assertJsonPath('data.totalOutputBoxes', 4);
    }

    public function test_production_reconciliation_declared_totals_use_final_outputs_only(): void
    {
        [$production, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $parentProduct = $this->createValidProduct($species, $captureZone, '41');
        $finalProduct = $this->createValidProduct($species, $captureZone, '42');

        ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $parentProduct->id,
            'lot_id' => 'L-INTERMEDIATE-REC',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);

        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $finalProduct->id,
            'lot_id' => 'L-FINAL-REC',
            'boxes' => 4,
            'weight_kg' => 40,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/reconciliation');

        $response->assertOk()
            ->assertJsonPath('data.declared.weight_kg', 40)
            ->assertJsonPath('data.declared.boxes', 4);
    }

    public function test_production_totals_endpoint_uses_final_outputs_only(): void
    {
        [$production, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $parentProduct = $this->createValidProduct($species, $captureZone, '51');
        $finalProduct = $this->createValidProduct($species, $captureZone, '52');

        ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $parentProduct->id,
            'lot_id' => 'L-INTERMEDIATE-TOTALS',
            'boxes' => 10,
            'weight_kg' => 100,
        ]);

        ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $finalProduct->id,
            'lot_id' => 'L-FINAL-TOTALS',
            'boxes' => 4,
            'weight_kg' => 40,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions/'.$production->id.'/totals');

        $response->assertOk()
            ->assertJsonPath('data.totalOutputWeight', 40)
            ->assertJsonPath('data.totalOutputBoxes', 4);
    }

    public function test_sync_outputs_allows_empty_array_and_deletes_existing_outputs(): void
    {
        [, , $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '61');

        $output = ProductionOutput::create([
            'production_record_id' => $childRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-DELETE-ALL',
            'boxes' => 2,
            'weight_kg' => 20,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/production-records/'.$childRecord->id.'/outputs', [
                'outputs' => [],
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.created', 0)
            ->assertJsonPath('summary.updated', 0)
            ->assertJsonPath('summary.deleted', 1);

        $this->assertNull(ProductionOutput::query()->find($output->id));
    }

    public function test_sync_outputs_with_empty_array_rejects_deletion_when_output_has_consumptions(): void
    {
        [, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '62');

        $output = ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-WITH-CONSUMPTION',
            'boxes' => 3,
            'weight_kg' => 30,
        ]);

        ProductionOutputConsumption::create([
            'production_record_id' => $childRecord->id,
            'production_output_id' => $output->id,
            'consumed_weight_kg' => 10,
            'consumed_boxes' => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/production-records/'.$parentRecord->id.'/outputs', [
                'outputs' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Error al sincronizar las salidas.');
    }

    public function test_sync_outputs_accepts_stock_product_sources_and_show_returns_product_data(): void
    {
        [, $parentRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $sourceProduct = $this->createValidProduct($species, $captureZone, '70');
        $outputProduct = $this->createValidProduct($species, $captureZone, '71');

        $boxA = Box::factory()->create([
            'article_id' => $sourceProduct->id,
            'net_weight' => 40,
            'gross_weight' => 41,
        ]);
        $boxB = Box::factory()->create([
            'article_id' => $sourceProduct->id,
            'net_weight' => 60,
            'gross_weight' => 61,
        ]);

        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $boxA->id,
        ]);
        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $boxB->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/production-records/'.$parentRecord->id.'/outputs', [
                'outputs' => [[
                    'product_id' => $outputProduct->id,
                    'lot_id' => 'L-STOCK-PRODUCT',
                    'boxes' => 5,
                    'weight_kg' => 80,
                    'sources' => [[
                        'source_type' => 'stock_product',
                        'product_id' => $sourceProduct->id,
                        'contributed_weight_kg' => 100,
                    ]],
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('data.outputs.0.sources.0.sourceType', 'stock_product')
            ->assertJsonPath('data.outputs.0.sources.0.productId', $sourceProduct->id)
            ->assertJsonPath('data.outputs.0.sources.0.contributedWeightKg', 100.0)
            ->assertJsonPath('data.outputs.0.sources.0.contributionPercentage', 100.0);

        $output = ProductionOutput::query()->where('production_record_id', $parentRecord->id)->firstOrFail();
        $this->assertDatabaseHas('production_output_sources', [
            'production_output_id' => $output->id,
            'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT,
            'product_id' => $sourceProduct->id,
        ]);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/production-records/'.$parentRecord->id);

        $showResponse->assertOk()
            ->assertJsonPath('data.outputs.0.sources.0.sourceType', 'stock_product')
            ->assertJsonPath('data.outputs.0.sources.0.productId', $sourceProduct->id)
            ->assertJsonPath('data.outputs.0.sources.0.product.id', $sourceProduct->id)
            ->assertJsonPath('data.outputs.0.sources.0.contributionPercentage', 100.0);
    }

    public function test_sync_outputs_rejects_legacy_production_input_id_for_stock_product_sources(): void
    {
        [, $parentRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $sourceProduct = $this->createValidProduct($species, $captureZone, '72');
        $outputProduct = $this->createValidProduct($species, $captureZone, '73');

        $box = Box::factory()->create([
            'article_id' => $sourceProduct->id,
            'net_weight' => 25,
            'gross_weight' => 26,
        ]);
        $input = ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $box->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/production-records/'.$parentRecord->id.'/outputs', [
                'outputs' => [[
                    'product_id' => $outputProduct->id,
                    'lot_id' => 'L-LEGACY-SOURCE',
                    'boxes' => 1,
                    'weight_kg' => 20,
                    'sources' => [[
                        'source_type' => 'stock_product',
                        'product_id' => $sourceProduct->id,
                        'production_input_id' => $input->id,
                        'contributed_weight_kg' => 25,
                    ]],
                ]],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('outputs.0.sources.0.production_input_id');
    }

    public function test_sync_outputs_keeps_existing_sources_and_creates_new_output_without_sources(): void
    {
        [, $parentRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $sourceProduct = $this->createValidProduct($species, $captureZone, '74');
        $existingOutputProduct = $this->createValidProduct($species, $captureZone, '75');
        $newOutputProduct = $this->createValidProduct($species, $captureZone, '76');

        $boxA = Box::factory()->create([
            'article_id' => $sourceProduct->id,
            'net_weight' => 40,
            'gross_weight' => 41,
        ]);
        $boxB = Box::factory()->create([
            'article_id' => $sourceProduct->id,
            'net_weight' => 60,
            'gross_weight' => 61,
        ]);

        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $boxA->id,
        ]);
        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $boxB->id,
        ]);

        $existingOutput = ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $existingOutputProduct->id,
            'lot_id' => 'L-KEEP-SOURCE',
            'boxes' => 5,
            'weight_kg' => 80,
        ]);

        ProductionOutputSource::create([
            'production_output_id' => $existingOutput->id,
            'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT,
            'product_id' => $sourceProduct->id,
            'contributed_weight_kg' => 100,
            'contributed_boxes' => 0,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/production-records/'.$parentRecord->id.'/outputs', [
                'outputs' => [
                    [
                        'id' => $existingOutput->id,
                        'product_id' => $existingOutputProduct->id,
                        'lot_id' => 'L-KEEP-SOURCE',
                        'boxes' => 5,
                        'weight_kg' => 80,
                        'sources' => [[
                            'source_type' => 'stock_product',
                            'product_id' => $sourceProduct->id,
                            'contributed_weight_kg' => 100,
                        ]],
                    ],
                    [
                        'product_id' => $newOutputProduct->id,
                        'lot_id' => 'L-NO-SOURCES',
                        'boxes' => 4,
                        'weight_kg' => 20,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.updated', 1)
            ->assertJsonCount(2, 'data.outputs');

        $existingOutput->refresh();
        $newOutput = ProductionOutput::query()
            ->where('production_record_id', $parentRecord->id)
            ->where('product_id', $newOutputProduct->id)
            ->firstOrFail();

        $this->assertCount(1, $existingOutput->sources()->get());
        $this->assertCount(0, $newOutput->sources()->get());
        $this->assertEquals(
            100.0,
            (float) ProductionOutputSource::query()
                ->where('source_type', ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT)
                ->where('product_id', $sourceProduct->id)
                ->sum('contributed_weight_kg')
        );
    }

    public function test_sync_outputs_rejects_global_source_overconsumption_across_outputs(): void
    {
        [, $parentRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $sourceProduct = $this->createValidProduct($species, $captureZone, '77');
        $outputProductA = $this->createValidProduct($species, $captureZone, '78');
        $outputProductB = $this->createValidProduct($species, $captureZone, '79');

        $boxA = Box::factory()->create([
            'article_id' => $sourceProduct->id,
            'net_weight' => 40,
            'gross_weight' => 41,
        ]);
        $boxB = Box::factory()->create([
            'article_id' => $sourceProduct->id,
            'net_weight' => 60,
            'gross_weight' => 61,
        ]);

        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $boxA->id,
        ]);
        ProductionInput::create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $boxB->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/production-records/'.$parentRecord->id.'/outputs', [
                'outputs' => [
                    [
                        'product_id' => $outputProductA->id,
                        'lot_id' => 'L-OVER-1',
                        'boxes' => 4,
                        'weight_kg' => 60,
                        'sources' => [[
                            'source_type' => 'stock_product',
                            'product_id' => $sourceProduct->id,
                            'contributed_weight_kg' => 80,
                        ]],
                    ],
                    [
                        'product_id' => $outputProductB->id,
                        'lot_id' => 'L-OVER-2',
                        'boxes' => 4,
                        'weight_kg' => 60,
                        'sources' => [[
                            'source_type' => 'stock_product',
                            'product_id' => $sourceProduct->id,
                            'contributed_weight_kg' => 40,
                        ]],
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Error al sincronizar las salidas.');

        $this->assertStringContainsString(
            'supera la disponibilidad',
            (string) $response->json('error')
        );
        $this->assertDatabaseCount('production_outputs', 0);
        $this->assertDatabaseCount('production_output_sources', 0);
    }

    public function test_sync_consumptions_allows_empty_array_and_deletes_existing_consumptions(): void
    {
        [, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '63');

        $output = ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-CONSUMPTION-DELETE',
            'boxes' => 4,
            'weight_kg' => 40,
        ]);

        $consumption = ProductionOutputConsumption::create([
            'production_record_id' => $childRecord->id,
            'production_output_id' => $output->id,
            'consumed_weight_kg' => 8,
            'consumed_boxes' => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/production-records/'.$childRecord->id.'/parent-output-consumptions', [
                'consumptions' => [],
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.created', 0)
            ->assertJsonPath('summary.updated', 0)
            ->assertJsonPath('summary.deleted', 1);

        $this->assertNull(ProductionOutputConsumption::query()->find($consumption->id));
    }

    public function test_sync_consumptions_accepts_root_json_empty_array_to_delete_all(): void
    {
        [, $parentRecord, $childRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, '64');

        $output = ProductionOutput::create([
            'production_record_id' => $parentRecord->id,
            'product_id' => $product->id,
            'lot_id' => 'L-ROOT-EMPTY',
            'boxes' => 2,
            'weight_kg' => 20,
        ]);

        $consumption = ProductionOutputConsumption::create([
            'production_record_id' => $childRecord->id,
            'production_output_id' => $output->id,
            'consumed_weight_kg' => 5,
            'consumed_boxes' => 0,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/production-records/'.$childRecord->id.'/parent-output-consumptions', []);

        $response->assertOk()
            ->assertJsonPath('summary.deleted', 1);

        $this->assertNull(ProductionOutputConsumption::query()->find($consumption->id));
    }

    public function test_releasing_all_boxes_moves_processed_pallet_back_to_registered(): void
    {
        [, $parentRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, 'RELEASE-ALL');

        $pallet = Pallet::create([
            'status' => Pallet::STATE_REGISTERED,
            'observations' => 'Pallet para test de liberacion',
        ]);

        $box = Box::factory()->create([
            'article_id' => $product->id,
            'net_weight' => 20,
            'gross_weight' => 21,
        ]);
        PalletBox::create([
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);

        $service = new ProductionInputService;
        $input = $service->create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $box->id,
        ]);

        $pallet->refresh();
        $this->assertSame(Pallet::STATE_PROCESSED, $pallet->status);

        $service->delete($input);

        $pallet->refresh();
        $this->assertSame(Pallet::STATE_REGISTERED, $pallet->status);
    }

    public function test_stored_sibling_pallet_keeps_state_when_other_pallet_is_released(): void
    {
        [, $parentRecord] = $this->createProductionWithRecords();
        $species = Species::query()->firstOrFail();
        $captureZone = CaptureZone::query()->firstOrFail();
        $product = $this->createValidProduct($species, $captureZone, 'STORED-SIBLING');

        $supplier = Supplier::query()->first() ?? Supplier::create([
            'name' => 'Proveedor test recepción',
            'type' => 'supplier',
            'emails' => 'proveedor@test.com',
        ]);
        $store = Store::query()->first() ?? Store::create([
            'name' => 'Almacen test',
            'temperature' => 0,
            'capacity' => 1000,
            'map' => json_encode([]),
            'store_type' => 'interno',
        ]);

        $reception = RawMaterialReception::create([
            'supplier_id' => $supplier->id,
            'date' => now()->toDateString(),
            'creation_mode' => RawMaterialReception::CREATION_MODE_PALLETS,
        ]);

        $storedPallet = Pallet::create([
            'status' => Pallet::STATE_STORED,
            'reception_id' => $reception->id,
            'observations' => 'Pallet almacenado no tocado',
        ]);
        StoredPallet::create([
            'pallet_id' => $storedPallet->id,
            'store_id' => $store->id,
        ]);

        $storedBox = Box::factory()->create([
            'article_id' => $product->id,
            'net_weight' => 12,
            'gross_weight' => 13,
        ]);
        PalletBox::create([
            'pallet_id' => $storedPallet->id,
            'box_id' => $storedBox->id,
        ]);

        $processedPallet = Pallet::create([
            'status' => Pallet::STATE_REGISTERED,
            'reception_id' => $reception->id,
            'observations' => 'Pallet que se consume completo',
        ]);
        $processedBox = Box::factory()->create([
            'article_id' => $product->id,
            'net_weight' => 30,
            'gross_weight' => 31,
        ]);
        PalletBox::create([
            'pallet_id' => $processedPallet->id,
            'box_id' => $processedBox->id,
        ]);

        $service = new ProductionInputService;
        $input = $service->create([
            'production_record_id' => $parentRecord->id,
            'box_id' => $processedBox->id,
        ]);
        $service->delete($input);

        // Simula un recálculo accidental sobre palet hermano de la misma recepción.
        $storedPallet->updateStateBasedOnBoxes();
        $storedPallet->refresh();

        $this->assertSame(Pallet::STATE_STORED, $storedPallet->status);
        $this->assertNotNull($storedPallet->storedPallet()->first());
    }
}
