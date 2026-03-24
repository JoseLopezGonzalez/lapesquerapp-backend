<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CaptureZone;
use App\Models\FishingGear;
use App\Models\Process;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionOutput;
use App\Models\ProductionOutputConsumption;
use App\Models\ProductionRecord;
use App\Models\Species;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for A.6 Production block: productions index, require auth.
 * Production is complex; this file covers flujos básicos de listado y auth.
 */
class ProductionBlockApiTest extends TestCase
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
        $slug = 'production-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Production',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User Production',
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
        $gear = FishingGear::create(['name' => 'Nasas']);
        $species = Species::create([
            'name' => 'Bacalao',
            'scientific_name' => 'Gadus',
            'fao' => 'COD',
            'image' => 'https://example.com/species-cod.png',
            'fishing_gear_id' => $gear->id,
        ]);
        $captureZone = CaptureZone::factory()->create();

        $production = Production::create([
            'lot' => 'LOT-' . uniqid(),
            'date' => now()->toDateString(),
            'species_id' => $species->id,
            'capture_zone_id' => $captureZone->id,
            'opened_at' => now(),
        ]);

        $parentProcess = Process::create(['name' => 'Corte', 'type' => 'process']);
        $childProcess = Process::create(['name' => 'Envasado', 'type' => 'final']);

        $parentRecord = ProductionRecord::create([
            'production_id' => $production->id,
            'process_id' => $parentProcess->id,
            'started_at' => now()->subHour(),
        ]);

        $childRecord = ProductionRecord::create([
            'production_id' => $production->id,
            'parent_record_id' => $parentRecord->id,
            'process_id' => $childProcess->id,
            'started_at' => now(),
        ]);

        return [$production, $parentRecord, $childRecord];
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
}
