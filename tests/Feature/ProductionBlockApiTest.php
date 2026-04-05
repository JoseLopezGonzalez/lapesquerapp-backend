<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\CaptureZone;
use App\Models\FishingGear;
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
use App\Models\Species;
use App\Models\Tax;
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

    public function test_productions_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/productions');

        $response->assertUnauthorized();
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
}
