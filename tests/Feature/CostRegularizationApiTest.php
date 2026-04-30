<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Box;
use App\Models\CaptureZone;
use App\Models\FishingGear;
use App\Models\Order;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Species;
use App\Models\Store;
use App\Models\StoredPallet;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class CostRegularizationApiTest extends TestCase
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
        $this->createTenantAndUser(Role::Administrador->value);
    }

    public function test_lists_finished_sales_boxes_missing_costs(): void
    {
        $product = $this->createProduct();
        $finishedOrder = Order::factory()->finished()->create([
            'entry_date' => '2026-03-10',
            'load_date' => '2026-03-15',
        ]);
        $pendingOrder = Order::factory()->pending()->create([
            'entry_date' => '2026-03-10',
            'load_date' => '2026-03-15',
        ]);

        $missingBox = $this->createBoxOnPallet($product, [
            'order_id' => $finishedOrder->id,
            'status' => Pallet::STATE_SHIPPED,
        ], 'LOT-MISSING-SALE');
        $this->createBoxOnPallet($product, [
            'order_id' => $pendingOrder->id,
            'status' => Pallet::STATE_REGISTERED,
        ], 'LOT-PENDING-SALE');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/cost-regularization/sales/missing-cost-boxes?'.http_build_query([
                'dateFrom' => '2026-03-01',
                'dateTo' => '2026-03-31',
            ]));

        $response->assertOk()
            ->assertJsonPath('summary.boxesCount', 1)
            ->assertJsonPath('lotProducts.0.lot', 'LOT-MISSING-SALE')
            ->assertJsonPath('lotProducts.0.boxesCount', 1)
            ->assertJsonPath('boxes.0.id', $missingBox->id)
            ->assertJsonPath('boxes.0.orderId', $finishedOrder->id);
    }

    public function test_lists_current_stock_boxes_missing_costs(): void
    {
        $product = $this->createProduct();
        $store = Store::factory()->create();
        $pendingOrder = Order::factory()->pending()->create();
        $finishedOrder = Order::factory()->finished()->create();

        $stockBox = $this->createBoxOnPallet($product, [
            'order_id' => $pendingOrder->id,
            'status' => Pallet::STATE_STORED,
        ], 'LOT-STOCK-MISSING');
        StoredPallet::create([
            'pallet_id' => $stockBox->pallet->id,
            'store_id' => $store->id,
        ]);

        $this->createBoxOnPallet($product, [
            'order_id' => $finishedOrder->id,
            'status' => Pallet::STATE_STORED,
        ], 'LOT-FINISHED-STOCK');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/cost-regularization/stock/missing-cost-boxes?'.http_build_query([
                'storeIds' => [$store->id],
            ]));

        $response->assertOk()
            ->assertJsonPath('summary.boxesCount', 1)
            ->assertJsonPath('lotProducts.0.lot', 'LOT-STOCK-MISSING')
            ->assertJsonPath('lotProducts.0.boxesCount', 1)
            ->assertJsonPath('boxes.0.id', $stockBox->id)
            ->assertJsonPath('boxes.0.store.id', $store->id);
    }

    public function test_applies_manual_costs_by_product_for_sales_scope(): void
    {
        $product = $this->createProduct();
        $order = Order::factory()->finished()->create([
            'entry_date' => '2026-03-10',
            'load_date' => '2026-03-15',
        ]);
        $box = $this->createBoxOnPallet($product, [
            'order_id' => $order->id,
            'status' => Pallet::STATE_SHIPPED,
        ], 'LOT-APPLY-SALE');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/cost-regularization/manual-costs/apply-by-product', [
                'scope' => 'sales',
                'filters' => [
                    'dateFrom' => '2026-03-01',
                    'dateTo' => '2026-03-31',
                ],
                'productCosts' => [
                    ['productId' => $product->id, 'manualCostPerKg' => 2.75],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('updatedBoxesCount', 1)
            ->assertJsonPath('products.0.manualCostPerKg', 2.75);

        $this->assertEquals(2.75, (float) $box->refresh()->manual_cost_per_kg);
    }

    public function test_applies_manual_costs_by_product_with_flat_sales_payload(): void
    {
        $product = $this->createProduct();
        $order = Order::factory()->finished()->create([
            'entry_date' => '2026-03-10',
            'load_date' => '2026-03-15',
        ]);
        $box = $this->createBoxOnPallet($product, [
            'order_id' => $order->id,
            'status' => Pallet::STATE_SHIPPED,
        ], 'LOT-APPLY-FLAT-SALE');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/cost-regularization/manual-costs/apply-by-product', [
                'scope' => 'sales',
                'products' => [
                    ['productId' => $product->id, 'manualCostPerKg' => 13.07],
                ],
                'dateFrom' => '2026-03-01',
                'dateTo' => '2026-03-31',
            ]);

        $response->assertOk()
            ->assertJsonPath('updatedBoxesCount', 1)
            ->assertJsonPath('products.0.manualCostPerKg', 13.07);

        $this->assertEquals(13.07, (float) $box->refresh()->manual_cost_per_kg);
    }

    public function test_applies_manual_costs_by_lot_product_for_stock_scope(): void
    {
        $product = $this->createProduct();
        $targetBox = $this->createBoxOnPallet($product, [
            'order_id' => null,
            'status' => Pallet::STATE_REGISTERED,
        ], 'LOT-APPLY-STOCK-A');
        $otherLotBox = $this->createBoxOnPallet($product, [
            'order_id' => null,
            'status' => Pallet::STATE_REGISTERED,
        ], 'LOT-APPLY-STOCK-B');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/cost-regularization/manual-costs/apply-by-lot-product', [
                'scope' => 'stock',
                'filters' => [
                    'productIds' => [$product->id],
                ],
                'lotProductCosts' => [
                    [
                        'productId' => $product->id,
                        'lot' => 'LOT-APPLY-STOCK-A',
                        'manualCostPerKg' => 3.15,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('updatedBoxesCount', 1)
            ->assertJsonPath('lotProducts.0.lot', 'LOT-APPLY-STOCK-A')
            ->assertJsonPath('lotProducts.0.manualCostPerKg', 3.15);

        $this->assertEquals(3.15, (float) $targetBox->refresh()->manual_cost_per_kg);
        $this->assertNull($otherLotBox->refresh()->manual_cost_per_kg);
    }

    public function test_rejects_non_admin_roles(): void
    {
        $this->createTenantAndUser(Role::Comercial->value);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/cost-regularization/stock/missing-cost-boxes');

        $response->assertForbidden();
    }

    private function createTenantAndUser(string $role): void
    {
        $database = config('database.connections.'.config('database.default').'.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'cost-reg-'.uniqid();
        Tenant::create([
            'name' => 'Test Tenant Cost Regularization',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User Cost Regularization',
            'email' => $slug.'@test.com',
            'password' => bcrypt('password'),
            'role' => $role,
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

    private function createProduct(): Product
    {
        $species = Species::factory()->create([
            'fishing_gear_id' => FishingGear::factory()->create()->id,
        ]);

        return Product::factory()->create([
            'species_id' => $species->id,
            'capture_zone_id' => CaptureZone::factory()->create()->id,
            'family_id' => ProductFamily::factory()->create()->id,
        ]);
    }

    private function createBoxOnPallet(Product $product, array $palletData, string $lot): Box
    {
        $pallet = Pallet::factory()->create($palletData);
        $box = Box::factory()->create([
            'article_id' => $product->id,
            'lot' => $lot,
            'net_weight' => 10.0,
            'gross_weight' => 10.5,
        ]);

        PalletBox::create([
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);

        return $box->refresh();
    }
}
