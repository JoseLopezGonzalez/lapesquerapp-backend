<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Box;
use App\Models\CaptureZone;
use App\Models\FishingGear;
use App\Models\Process;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionInput;
use App\Models\ProductionOutput;
use App\Models\ProductionOutputConsumption;
use App\Models\ProductionOutputSource;
use App\Models\ProductionRecord;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProductionScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for A.6 Production block: productions index, require auth.
 * Production is complex; this file covers flujos básicos de listado y auth.
 */
class ProductionBlockApiTest extends TestCase
{
    use BuildsProductionScenario;
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
        $slug = 'production-' . uniqid();
        $scenario = $this->createProductionScenario($slug);

        $this->token = $scenario['user']->createToken('test')->plainTextToken;
        $this->tenantSubdomain = $scenario['slug'];
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer ' . $this->token,
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
            'name' => 'Producto ' . $suffix,
            'species_id' => $species->id,
            'capture_zone_id' => $captureZone->id,
            'article_gtin' => 'A' . str_pad($suffix, 12, '1'),
            'box_gtin' => 'B' . str_pad($suffix, 12, '2'),
            'pallet_gtin' => 'P' . str_pad($suffix, 12, '3'),
            'fixed_weight' => 1.50,
            'facil_com_code' => 'FC-' . $suffix,
        ]);
    }

    public function test_production_record_show_returns_parent_with_process(): void
    {
        [, $parentRecord, $childRecord] = $this->createProductionWithRecords();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/production-records/' . $childRecord->id);

        $response->assertOk()
            ->assertJsonPath('data.parent.id', $parentRecord->id)
            ->assertJsonPath('data.parent.process.id', $parentRecord->process_id)
            ->assertJsonPath('data.parent.process.name', 'Corte');
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
            ->getJson('/api/v2/production-output-consumptions/available-outputs/' . $childRecord->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.output.id', $output->id)
            ->assertJsonPath('data.0.hasExistingConsumption', true);
    }

    public function test_production_records_root_only_filter_behaves_correctly(): void
    {
        [$production, $parentRecord, $childRecord] = $this->createProductionWithRecords();

        $responseRootTrue = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/production-records?production_id=' . $production->id . '&root_only=1');

        $responseRootFalse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/production-records?production_id=' . $production->id . '&root_only=0');

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
            ->putJson('/api/v2/production-records/' . $childRecord->id . '/parent-output-consumptions', [
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
            ->getJson('/api/v2/productions/' . $production->id);

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
            ->getJson('/api/v2/productions/' . $production->id . '/reconciliation');

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
            ->getJson('/api/v2/productions/' . $production->id . '/totals');

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
            ->putJson('/api/v2/production-records/' . $childRecord->id . '/outputs', [
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
            ->putJson('/api/v2/production-records/' . $parentRecord->id . '/outputs', [
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
            ->putJson('/api/v2/production-records/' . $parentRecord->id . '/outputs', [
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
            ->assertJsonPath('data.outputs.0.sources.0.contributedWeightKg', 100.0);

        $output = ProductionOutput::query()->where('production_record_id', $parentRecord->id)->firstOrFail();
        $this->assertDatabaseHas('production_output_sources', [
            'production_output_id' => $output->id,
            'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT,
            'product_id' => $sourceProduct->id,
        ]);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/production-records/' . $parentRecord->id);

        $showResponse->assertOk()
            ->assertJsonPath('data.outputs.0.sources.0.sourceType', 'stock_product')
            ->assertJsonPath('data.outputs.0.sources.0.productId', $sourceProduct->id)
            ->assertJsonPath('data.outputs.0.sources.0.product.id', $sourceProduct->id);
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
            ->putJson('/api/v2/production-records/' . $parentRecord->id . '/outputs', [
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
            ->putJson('/api/v2/production-records/' . $childRecord->id . '/parent-output-consumptions', [
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
            ->putJson('/api/v2/production-records/' . $childRecord->id . '/parent-output-consumptions', []);

        $response->assertOk()
            ->assertJsonPath('summary.deleted', 1);

        $this->assertNull(ProductionOutputConsumption::query()->find($consumption->id));
    }
}
