<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Box;
use App\Models\CaptureZone;
use App\Models\FishingGear;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\RawMaterialReception;
use App\Models\RawMaterialReceptionProduct;
use App\Models\Species;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderStatisticsApiTest extends TestCase
{
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
        $database = config('database.connections.'.config('database.default').'.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'stats-'.uniqid();
        Tenant::create([
            'name' => 'Test Tenant',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => $slug.'@test.com',
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
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_get_total_net_weight_stats(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/statistics/orders/total-net-weight?'.http_build_query([
                'dateFrom' => '2024-01-01',
                'dateTo' => '2024-12-31',
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'value',
            'comparisonValue',
            'percentageChange',
            'range' => [
                'from',
                'to',
                'fromPrev',
                'toPrev',
            ],
        ]);
    }

    public function test_can_export_orders_report(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->get('/api/v2/orders_report');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.ms-excel');
    }

    public function test_can_get_total_amount_stats(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/statistics/orders/total-amount?'.http_build_query([
                'dateFrom' => '2024-01-01',
                'dateTo' => '2024-12-31',
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'value',
            'subtotal',
            'tax',
            'comparisonValue',
            'comparisonSubtotal',
            'comparisonTax',
            'percentageChange',
            'range' => [
                'from',
                'to',
                'fromPrev',
                'toPrev',
            ],
        ]);
    }

    public function test_can_get_order_ranking_stats(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/statistics/orders/ranking?'.http_build_query([
                'groupBy' => 'client',
                'valueType' => 'totalAmount',
                'dateFrom' => '2024-01-01',
                'dateTo' => '2024-12-31',
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([]);
        $this->assertIsArray($response->json());
    }

    public function test_can_get_sales_chart_data(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/orders/sales-chart-data?'.http_build_query([
                'dateFrom' => '2024-01-01',
                'dateTo' => '2024-12-31',
                'valueType' => 'quantity',
                'groupBy' => 'day',
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([]);
        $this->assertIsArray($response->json());
    }

    public function test_profitability_products_includes_per_kg_metrics(): void
    {
        $species = Species::factory()->create([
            'fishing_gear_id' => FishingGear::factory()->create()->id,
        ]);

        $product = Product::factory()->create([
            'name' => 'Merluza test',
            'species_id' => $species->id,
            'capture_zone_id' => CaptureZone::factory()->create()->id,
            'family_id' => ProductFamily::factory()->create()->id,
        ]);

        $reception = RawMaterialReception::factory()->create();
        $order = Order::factory()->create([
            'entry_date' => '2026-03-10',
            'load_date' => '2026-03-15',
        ]);

        OrderPlannedProductDetail::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 5.0,
        ]);

        $pallet = Pallet::factory()->create([
            'order_id' => $order->id,
            'reception_id' => $reception->id,
            'status' => Pallet::STATE_SHIPPED,
        ]);

        RawMaterialReceptionProduct::factory()->create([
            'reception_id' => $reception->id,
            'product_id' => $product->id,
            'lot' => 'LOT-001',
            'price' => 3.0,
        ]);

        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => 'LOT-001',
            'net_weight' => 10.0,
            'gross_weight' => 10.5,
        ]);

        PalletBox::create([
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/statistics/orders/profitability-products?'.http_build_query([
                'dateFrom' => '2026-03-01',
                'dateTo' => '2026-03-31',
            ]));

        $response->assertStatus(200)
            ->assertJsonPath('period.from', '2026-03-01')
            ->assertJsonPath('period.to', '2026-03-31')
            ->assertJsonPath('products.0.product.id', $product->id)
            ->assertJsonPath('products.0.product.name', 'Merluza test')
            ->assertJsonPath('products.0.totalWeightKg', 10)
            ->assertJsonPath('products.0.totalRevenue', 50)
            ->assertJsonPath('products.0.totalCost', 30)
            ->assertJsonPath('products.0.grossMargin', 20)
            ->assertJsonPath('products.0.marginPercentage', 40)
            ->assertJsonPath('products.0.revenuePerKg', 5)
            ->assertJsonPath('products.0.costPerKg', 3)
            ->assertJsonPath('products.0.marginPerKg', 2)
            ->assertJsonPath('products.0.ordersCount', 1);
    }

    public function test_profitability_summary_includes_cost_coverage_by_boxes(): void
    {
        $species = Species::factory()->create([
            'fishing_gear_id' => FishingGear::factory()->create()->id,
        ]);

        $product = Product::factory()->create([
            'name' => 'Merluza cobertura test',
            'species_id' => $species->id,
            'capture_zone_id' => CaptureZone::factory()->create()->id,
            'family_id' => ProductFamily::factory()->create()->id,
        ]);

        $reception = RawMaterialReception::factory()->create();
        $order = Order::factory()->create([
            'entry_date' => '2026-03-10',
            'load_date' => '2026-03-15',
        ]);

        OrderPlannedProductDetail::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 5.0,
        ]);

        $pallet = Pallet::factory()->create([
            'order_id' => $order->id,
            'reception_id' => $reception->id,
            'status' => Pallet::STATE_SHIPPED,
        ]);

        RawMaterialReceptionProduct::factory()->create([
            'reception_id' => $reception->id,
            'product_id' => $product->id,
            'lot' => 'LOT-COVERED',
            'price' => 3.0,
        ]);

        $coveredBox = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => 'LOT-COVERED',
            'net_weight' => 10.0,
            'gross_weight' => 10.5,
        ]);
        $uncoveredBox = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => 'LOT-UNCOVERED',
            'net_weight' => 5.0,
            'gross_weight' => 5.2,
        ]);

        PalletBox::create(['pallet_id' => $pallet->id, 'box_id' => $coveredBox->id]);
        PalletBox::create(['pallet_id' => $pallet->id, 'box_id' => $uncoveredBox->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/statistics/orders/profitability-summary?'.http_build_query([
                'dateFrom' => '2026-03-01',
                'dateTo' => '2026-03-31',
            ]));

        $response->assertOk()
            ->assertJsonPath('totalRevenue', 75)
            ->assertJsonPath('totalCost', 30)
            ->assertJsonPath('coveredBoxes', 1)
            ->assertJsonPath('uncoveredBoxes', 1)
            ->assertJsonPath('costCoverageBoxesPct', 50);
    }

    public function test_can_export_profitability_summary_audit_excel(): void
    {
        $species = Species::factory()->create([
            'fishing_gear_id' => FishingGear::factory()->create()->id,
        ]);

        $product = Product::factory()->create([
            'name' => 'Merluza export test',
            'species_id' => $species->id,
            'capture_zone_id' => CaptureZone::factory()->create()->id,
            'family_id' => ProductFamily::factory()->create()->id,
        ]);

        $reception = RawMaterialReception::factory()->create();
        $order = Order::factory()->create([
            'entry_date' => '2026-03-10',
            'load_date' => '2026-03-15',
        ]);

        OrderPlannedProductDetail::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 5.0,
        ]);

        $pallet = Pallet::factory()->create([
            'order_id' => $order->id,
            'reception_id' => $reception->id,
            'status' => Pallet::STATE_SHIPPED,
        ]);

        RawMaterialReceptionProduct::factory()->create([
            'reception_id' => $reception->id,
            'product_id' => $product->id,
            'lot' => 'LOT-EXPORT-001',
            'price' => 3.0,
        ]);

        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => 'LOT-EXPORT-001',
            'net_weight' => 10.0,
            'gross_weight' => 10.5,
        ]);

        PalletBox::create([
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->get('/api/v2/statistics/orders/profitability-summary/export?'.http_build_query([
                'dateFrom' => '2026-03-01',
                'dateTo' => '2026-03-31',
            ]));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'order_profitability_summary_2026-03-01_2026-03-31.xlsx',
            $response->headers->get('content-disposition')
        );
    }

    public function test_can_create_and_download_profitability_summary_export_job(): void
    {
        $species = Species::factory()->create([
            'fishing_gear_id' => FishingGear::factory()->create()->id,
        ]);

        $product = Product::factory()->create([
            'name' => 'Merluza async export test',
            'species_id' => $species->id,
            'capture_zone_id' => CaptureZone::factory()->create()->id,
            'family_id' => ProductFamily::factory()->create()->id,
        ]);

        $reception = RawMaterialReception::factory()->create();
        $order = Order::factory()->create([
            'entry_date' => '2026-03-10',
            'load_date' => '2026-03-15',
        ]);

        OrderPlannedProductDetail::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 5.0,
        ]);

        $pallet = Pallet::factory()->create([
            'order_id' => $order->id,
            'reception_id' => $reception->id,
            'status' => Pallet::STATE_SHIPPED,
        ]);

        RawMaterialReceptionProduct::factory()->create([
            'reception_id' => $reception->id,
            'product_id' => $product->id,
            'lot' => 'LOT-ASYNC-001',
            'price' => 3.0,
        ]);

        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => 'LOT-ASYNC-001',
            'net_weight' => 10.0,
            'gross_weight' => 10.5,
        ]);

        PalletBox::create([
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);

        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/statistics/orders/profitability-summary/export-jobs', [
                'dateFrom' => '2026-03-01',
                'dateTo' => '2026-03-31',
                'onlyMissingCosts' => true,
            ]);

        $createResponse->assertStatus(202)
            ->assertJsonStructure([
                'id',
                'status',
                'filters',
                'downloadUrl',
            ]);

        $exportId = $createResponse->json('id');

        $statusResponse = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/statistics/orders/profitability-summary/export-jobs/{$exportId}");

        $statusResponse->assertStatus(200)
            ->assertJsonPath('id', $exportId)
            ->assertJsonPath('status', 'finished');

        $downloadResponse = $this->withHeaders($this->authHeaders())
            ->get("/api/v2/statistics/orders/profitability-summary/export-jobs/{$exportId}/download");

        $downloadResponse->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $downloadResponse->headers->get('content-type')
        );
    }

    public function test_reception_chart_data_requires_authentication(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/raw-material-receptions/reception-chart-data?'.http_build_query([
            'dateFrom' => '2024-01-01',
            'dateTo' => '2024-12-31',
            'valueType' => 'quantity',
            'groupBy' => 'day',
        ]));

        $response->assertStatus(401);
    }

    public function test_dispatch_chart_data_requires_authentication(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/cebo-dispatches/dispatch-chart-data?'.http_build_query([
            'dateFrom' => '2024-01-01',
            'dateTo' => '2024-12-31',
            'valueType' => 'quantity',
            'groupBy' => 'day',
        ]));

        $response->assertStatus(401);
    }
}
