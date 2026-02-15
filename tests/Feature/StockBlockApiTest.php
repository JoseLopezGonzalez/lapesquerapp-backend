<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CeboDispatch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pallet;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\RawMaterialReception;
use App\Models\Salesperson;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\User;
use Database\Seeders\CaptureZonesSeeder;
use Database\Seeders\FishingGearSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\ProductFamilySeeder;
use Database\Seeders\SpeciesSeeder;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for Inventario/Stock block: raw-material-receptions, pallets, stores,
 * stock statistics and chart data endpoints. Uses tenant + Sanctum auth.
 */
class StockBlockApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?string $token = null;

    private ?string $tenantSubdomain = null;

    private bool $tenantSeededForReceptions = false;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantAndUser();
    }

    /**
     * Seed tenant with minimal data for raw material reception tests (supplier, product).
     * We do not run ProductSeeder from tests because it uses $this->command->info() which is null in PHPUnit.
     */
    private function seedTenantForReceptions(): void
    {
        if ($this->tenantSeededForReceptions) {
            return;
        }
        $this->tenantSeededForReceptions = true;
        (new ProductCategorySeeder)->run();
        (new ProductFamilySeeder)->run();
        (new CaptureZonesSeeder)->run();
        (new FishingGearSeeder)->run();
        (new SpeciesSeeder)->run();
        (new SupplierSeeder)->run();
        // Create one product manually (ProductSeeder uses $this->command in PHPUnit)
        $species = \App\Models\Species::first();
        $zone = \App\Models\CaptureZone::first();
        $family = \App\Models\ProductFamily::first();
        if ($species && $zone && $family && Product::count() === 0) {
            Product::create([
                'name' => 'Producto Test Recepciones',
                'species_id' => $species->id,
                'capture_zone_id' => $zone->id,
                'family_id' => $family->id,
                'article_gtin' => null,
                'box_gtin' => null,
                'pallet_gtin' => null,
            ]);
        }
    }

    /**
     * Create minimal Customer + Order on tenant for tests that need a pallet linked to an order.
     * Creates Country, PaymentTerm, Salesperson, Transport if missing.
     */
    private function createMinimalOrderOnTenant(): Order
    {
        $country = Country::on('tenant')->firstOrCreate(
            ['name' => 'España'],
            ['name' => 'España']
        );
        $paymentTerm = PaymentTerm::on('tenant')->firstOrCreate(
            ['name' => 'Test PT'],
            ['name' => 'Test PT']
        );
        $salesperson = Salesperson::on('tenant')->firstOrCreate(
            ['name' => 'Test Salesperson'],
            ['name' => 'Test Salesperson']
        );
        $transport = Transport::on('tenant')->firstOrCreate(
            ['name' => 'Test Transport'],
            [
                'name' => 'Test Transport',
                'vat_number' => 'ES00000000A',
                'address' => 'Test address',
                'emails' => 'test@test.com',
            ]
        );
        $customer = Customer::on('tenant')->firstOrCreate(
            ['name' => 'Test Customer Reception'],
            [
                'name' => 'Test Customer Reception',
                'vat_number' => 'ES12345678Z',
                'payment_term_id' => $paymentTerm->id,
                'billing_address' => 'Billing',
                'shipping_address' => 'Shipping',
                'salesperson_id' => $salesperson->id,
                'emails' => json_encode(['customer@test.com']),
                'contact_info' => 'Contact',
                'country_id' => $country->id,
                'transport_id' => $transport->id,
            ]
        );
        return Order::on('tenant')->create([
            'customer_id' => $customer->id,
            'payment_term_id' => $paymentTerm->id,
            'billing_address' => 'Billing',
            'shipping_address' => 'Shipping',
            'salesperson_id' => $salesperson->id,
            'emails' => json_encode(['order@test.com']),
            'transport_id' => $transport->id,
            'entry_date' => now()->format('Y-m-d'),
            'load_date' => now()->format('Y-m-d'),
            'status' => Order::STATUS_PENDING,
        ]);
    }

    private function createTenantAndUser(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'stock-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Stock',
            'subdomain' => $slug,
            'database' => $database,
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Test User Stock',
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

    public function test_can_list_raw_material_receptions(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/raw-material-receptions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_list_pallets(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/pallets');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_list_stores(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/stores');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_get_stock_total_stats(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/statistics/stock/total');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'totalNetWeight',
            'totalPallets',
            'totalBoxes',
            'totalSpecies',
            'totalStores',
        ]);
    }

    public function test_can_get_stock_by_species_stats(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/statistics/stock/total-by-species');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    public function test_reception_chart_data_returns_422_without_required_params(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/raw-material-receptions/reception-chart-data');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dateFrom', 'dateTo', 'valueType']);
    }

    public function test_reception_chart_data_returns_200_with_valid_params(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/raw-material-receptions/reception-chart-data?' . http_build_query([
                'dateFrom' => '2025-01-01',
                'dateTo' => '2025-01-31',
                'valueType' => 'quantity',
                'groupBy' => 'day',
            ]));

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    public function test_dispatch_chart_data_returns_422_without_required_params(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/cebo-dispatches/dispatch-chart-data');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dateFrom', 'dateTo', 'valueType']);
    }

    public function test_dispatch_chart_data_returns_200_with_valid_params(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/cebo-dispatches/dispatch-chart-data?' . http_build_query([
                'dateFrom' => '2025-01-01',
                'dateTo' => '2025-01-31',
                'valueType' => 'amount',
                'groupBy' => 'month',
            ]));

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    public function test_can_show_store(): void
    {
        $store = Store::create([
            'name' => 'Almacén Test ' . uniqid(),
            'temperature' => 2.5,
            'capacity' => 100,
            'map' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/stores/' . $store->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $store->id);
        $response->assertJsonPath('data.name', $store->name);
    }

    public function test_stock_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v2/raw-material-receptions', [
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenantSubdomain,
        ]);
        $response->assertStatus(401);
    }

    public function test_pallets_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/pallets');

        $response->assertStatus(401);
    }

    public function test_can_store_pallet(): void
    {
        $this->seedTenantForReceptions();
        $product = Product::on('tenant')->first();
        $this->assertNotNull($product);

        $payload = [
            'observations' => 'Palet test',
            'boxes' => [
                [
                    'product' => ['id' => $product->id],
                    'lot' => 'LOT-' . uniqid(),
                    'gs1128' => '01000000000000001' . str_pad(rand(1, 99), 2, '0'),
                    'grossWeight' => 10.5,
                    'netWeight' => 9.0,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/pallets', $payload);

        $response->assertStatus(201);
        $json = $response->json();
        $this->assertTrue(isset($json['id']) || isset($json['data']['id']), 'Response should have id');
        $this->assertDatabaseHas('pallets', ['observations' => 'Palet test'], 'tenant');
    }

    public function test_can_store_raw_material_reception_with_details(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $product = Product::on('tenant')->first();
        $this->assertNotNull($supplier, 'Tenant should have at least one supplier');
        $this->assertNotNull($product, 'Tenant should have at least one product');

        $payload = [
            'supplier' => ['id' => $supplier->id],
            'date' => now()->format('Y-m-d'),
            'notes' => 'Test reception',
            'details' => [
                [
                    'product' => ['id' => $product->id],
                    'netWeight' => 100.5,
                    'price' => 2.5,
                    'boxes' => 2,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/raw-material-receptions', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'date', 'creationMode', 'details']]);
        $response->assertJsonPath('data.creationMode', 'lines');
        $this->assertDatabaseHas('raw_material_receptions', ['supplier_id' => $supplier->id], 'tenant');
    }

    public function test_can_show_raw_material_reception(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $product = Product::on('tenant')->first();
        $reception = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'notes' => null,
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);
        $reception->products()->create([
            'product_id' => $product->id,
            'net_weight' => 50,
            'price' => 1.5,
            'lot' => 'TEST-LOT',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/raw-material-receptions/' . $reception->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $reception->id);
        $response->assertJsonPath('data.date', $reception->date);
    }

    public function test_can_update_raw_material_reception(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $product = Product::on('tenant')->first();
        $reception = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => '2020-03-10',
            'notes' => 'Notas iniciales',
            'declared_total_amount' => null,
            'declared_total_net_weight' => null,
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);
        $reception->products()->create([
            'product_id' => $product->id,
            'net_weight' => 100,
            'price' => 2,
            'lot' => 'LOT-OLD',
        ]);

        $payload = [
            'supplier' => ['id' => $supplier->id],
            'date' => '2020-03-10',
            'notes' => 'Notas actualizadas',
            'declaredTotalAmount' => 500,
            'declaredTotalNetWeight' => 200,
            'details' => [
                [
                    'product' => ['id' => $product->id],
                    'netWeight' => 120,
                    'price' => 2.5,
                    'lot' => 'LOT-NEW',
                    'boxes' => 3,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/raw-material-receptions/' . $reception->id, $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Recepción de materia prima actualizada correctamente.');
        $response->assertJsonPath('data.id', $reception->id);
        $response->assertJsonPath('data.notes', 'Notas actualizadas');
        $response->assertJsonPath('data.declaredTotalAmount', 500);
        $response->assertJsonPath('data.declaredTotalNetWeight', 200);

        $reception->refresh();
        $reception->load('products');
        $this->assertSame('Notas actualizadas', $reception->notes);
        $this->assertSame(500.0, (float) $reception->declared_total_amount);
        $this->assertSame(200.0, (float) $reception->declared_total_net_weight);
        // WriteService replaces details (delete + create), so assert on the new product row
        $updatedDetail = $reception->products()->first();
        $this->assertNotNull($updatedDetail);
        $this->assertSame(120.0, (float) $updatedDetail->net_weight);
        $this->assertSame(2.5, (float) $updatedDetail->price);
        $this->assertSame('LOT-NEW', $updatedDetail->lot);
        $this->assertSame(3, $updatedDetail->boxes);
    }

    public function test_can_destroy_raw_material_reception(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $reception = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'notes' => null,
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/raw-material-receptions/' . $reception->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('raw_material_receptions', ['id' => $reception->id], 'tenant');
    }

    public function test_can_destroy_multiple_raw_material_receptions(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $r1 = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);
        $r2 = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => now()->subDay()->format('Y-m-d'),
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/raw-material-receptions', ['ids' => [$r1->id, $r2->id]]);

        $response->assertStatus(200);
        $response->assertJsonPath('deleted', 2);
        $this->assertDatabaseMissing('raw_material_receptions', ['id' => $r1->id], 'tenant');
        $this->assertDatabaseMissing('raw_material_receptions', ['id' => $r2->id], 'tenant');
    }

    public function test_validate_bulk_update_declared_data_returns_structure(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $reception = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'declared_total_amount' => 100,
            'declared_total_net_weight' => 50,
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);

        $payload = [
            'receptions' => [
                [
                    'supplier_id' => $supplier->id,
                    'date' => $reception->date,
                    'declared_total_amount' => 150,
                    'declared_total_net_weight' => 60,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/raw-material-receptions/validate-bulk-update-declared-data', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure(['valid', 'invalid', 'ready_to_update', 'results']);
        $data = $response->json();
        $this->assertGreaterThanOrEqual(0, $data['valid']);
        $this->assertIsArray($data['results']);
    }

    public function test_bulk_update_declared_data_updates_receptions(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $uniqueDate = '2019-06-15'; // Fixed date so we only match this test's reception
        $reception = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => $uniqueDate,
            'declared_total_amount' => 100,
            'declared_total_net_weight' => 50,
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);

        $payload = [
            'receptions' => [
                [
                    'supplier_id' => $supplier->id,
                    'date' => $uniqueDate,
                    'declared_total_amount' => 999,
                    'declared_total_net_weight' => 75,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/raw-material-receptions/bulk-update-declared-data', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('updated', 1);

        // Assert via API show so we read with same tenant context as the update
        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/raw-material-receptions/' . $reception->id);
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('data.declaredTotalAmount', 999);
        $showResponse->assertJsonPath('data.declaredTotalNetWeight', 75);
    }

    public function test_destroy_reception_fails_when_pallet_stored(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $reception = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => '2021-01-10',
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);
        $pallet = Pallet::on('tenant')->create([
            'reception_id' => $reception->id,
            'status' => Pallet::STATE_STORED,
            'observations' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/raw-material-receptions/' . $reception->id);

        $response->assertStatus(500);
        $this->assertDatabaseHas('raw_material_receptions', ['id' => $reception->id], 'tenant');
    }

    public function test_destroy_reception_fails_when_pallet_linked_to_order(): void
    {
        $this->seedTenantForReceptions();
        $order = $this->createMinimalOrderOnTenant();
        $supplier = Supplier::on('tenant')->first();
        $reception = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => '2021-02-10',
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);
        $pallet = Pallet::on('tenant')->create([
            'reception_id' => $reception->id,
            'status' => Pallet::STATE_REGISTERED,
            'observations' => null,
        ]);
        $pallet->order_id = $order->id;
        $pallet->save();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/raw-material-receptions/' . $reception->id);

        $response->assertStatus(500);
        $this->assertDatabaseHas('raw_material_receptions', ['id' => $reception->id], 'tenant');
    }

    public function test_destroy_multiple_returns_422_when_one_has_pallet_linked_to_order(): void
    {
        $this->seedTenantForReceptions();
        $order = $this->createMinimalOrderOnTenant();
        $supplier = Supplier::on('tenant')->first();
        $r1 = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => '2021-03-01',
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);
        $r2 = RawMaterialReception::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => '2021-03-02',
            'creation_mode' => RawMaterialReception::CREATION_MODE_LINES,
        ]);
        $pallet = Pallet::on('tenant')->create([
            'reception_id' => $r1->id,
            'status' => Pallet::STATE_REGISTERED,
            'observations' => null,
        ]);
        $pallet->order_id = $order->id;
        $pallet->save();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/raw-material-receptions', ['ids' => [$r1->id, $r2->id]]);

        $response->assertStatus(422);
        $response->assertJsonPath('deleted', 1);
        $response->assertJsonPath('message', 'Algunas recepciones no pudieron eliminarse.');
        $response->assertJsonStructure(['errors' => [['id', 'message']]]);
        $this->assertDatabaseHas('raw_material_receptions', ['id' => $r1->id], 'tenant');
        $this->assertDatabaseMissing('raw_material_receptions', ['id' => $r2->id], 'tenant');
    }

    public function test_store_reception_returns_422_with_invalid_payload(): void
    {
        $this->seedTenantForReceptions();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/raw-material-receptions', [
                'date' => '2020-01-01',
                'details' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['supplier.id']);
    }

    public function test_bulk_update_declared_data_returns_422_with_invalid_payload(): void
    {
        $this->seedTenantForReceptions();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/raw-material-receptions/bulk-update-declared-data', [
                'receptions' => [
                    [
                        'supplier_id' => 999999,
                        'date' => '2020-01-01',
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['receptions.0.supplier_id']);
    }

    // --- Cebo Dispatches (Despachos de cebo) ---

    public function test_can_store_cebo_dispatch(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $product = Product::on('tenant')->first();
        $payload = [
            'supplier' => ['id' => $supplier->id],
            'date' => '2022-05-10',
            'notes' => 'Notas despacho test',
            'details' => [
                ['product' => ['id' => $product->id], 'netWeight' => 50],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/cebo-dispatches', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Despacho de cebo creado correctamente.');
        $response->assertJsonStructure(['data' => ['id', 'date', 'supplier', 'details']]);
        $this->assertDatabaseHas('cebo_dispatches', ['supplier_id' => $supplier->id], 'tenant');
    }

    public function test_can_show_cebo_dispatch(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $product = Product::on('tenant')->first();
        $dispatch = CeboDispatch::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => '2022-06-01',
            'notes' => null,
        ]);
        $dispatch->products()->create(['product_id' => $product->id, 'net_weight' => 100]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/cebo-dispatches/' . $dispatch->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $dispatch->id);
        $response->assertJsonPath('data.date', $dispatch->date);
    }

    public function test_can_update_cebo_dispatch(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $product = Product::on('tenant')->first();
        $dispatch = CeboDispatch::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => '2022-07-01',
            'notes' => 'Antes',
        ]);
        $dispatch->products()->create(['product_id' => $product->id, 'net_weight' => 80]);

        $payload = [
            'supplier' => ['id' => $supplier->id],
            'date' => '2022-07-01',
            'notes' => 'Después',
            'details' => [
                ['product' => ['id' => $product->id], 'netWeight' => 120],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/cebo-dispatches/' . $dispatch->id, $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Despacho de cebo actualizado correctamente.');
        $response->assertJsonPath('data.notes', 'Después');
        $dispatch->refresh();
        $dispatch->load('products');
        $this->assertSame('Después', $dispatch->notes);
        $this->assertSame(120.0, (float) $dispatch->products->first()->net_weight);
    }

    public function test_can_destroy_cebo_dispatch(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $dispatch = CeboDispatch::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => '2022-08-01',
            'notes' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/cebo-dispatches/' . $dispatch->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cebo_dispatches', ['id' => $dispatch->id], 'tenant');
    }

    public function test_can_destroy_multiple_cebo_dispatches(): void
    {
        $this->seedTenantForReceptions();
        $supplier = Supplier::on('tenant')->first();
        $d1 = CeboDispatch::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => '2022-09-01',
            'notes' => null,
        ]);
        $d2 = CeboDispatch::on('tenant')->create([
            'supplier_id' => $supplier->id,
            'date' => '2022-09-02',
            'notes' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/cebo-dispatches', ['ids' => [$d1->id, $d2->id]]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Despachos de cebo eliminados correctamente');
        $this->assertDatabaseMissing('cebo_dispatches', ['id' => $d1->id], 'tenant');
        $this->assertDatabaseMissing('cebo_dispatches', ['id' => $d2->id], 'tenant');
    }

    public function test_store_cebo_dispatch_returns_422_with_invalid_payload(): void
    {
        $this->seedTenantForReceptions();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/cebo-dispatches', [
                'date' => '2022-01-01',
                'details' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['supplier.id']);
    }
}
