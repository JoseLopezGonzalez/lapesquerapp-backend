<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
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
}
