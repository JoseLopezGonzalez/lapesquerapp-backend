<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderStatisticsApiTest extends TestCase
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
        $slug = 'stats-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User',
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

    public function test_can_get_total_net_weight_stats(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/statistics/orders/total-net-weight?' . http_build_query([
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
            ->getJson('/api/v2/statistics/orders/total-amount?' . http_build_query([
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
            ->getJson('/api/v2/statistics/orders/ranking?' . http_build_query([
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
            ->getJson('/api/v2/orders/sales-chart-data?' . http_build_query([
                'dateFrom' => '2024-01-01',
                'dateTo' => '2024-12-31',
                'valueType' => 'quantity',
                'groupBy' => 'day',
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([]);
        $this->assertIsArray($response->json());
    }

    public function test_reception_chart_data_requires_authentication(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/raw-material-receptions/reception-chart-data?' . http_build_query([
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
        ])->getJson('/api/v2/cebo-dispatches/dispatch-chart-data?' . http_build_query([
            'dateFrom' => '2024-01-01',
            'dateTo' => '2024-12-31',
            'valueType' => 'quantity',
            'groupBy' => 'day',
        ]));

        $response->assertStatus(401);
    }
}
