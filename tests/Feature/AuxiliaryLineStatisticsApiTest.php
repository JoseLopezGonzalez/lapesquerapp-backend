<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderAuxiliaryLine;
use App\Models\Tax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class AuxiliaryLineStatisticsApiTest extends TestCase
{
    use BuildsOperationsScenario;
    use ConfiguresTenantConnection;
    use RefreshDatabase;

    private ?string $token = null;

    private ?string $tenantSubdomain = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();

        // La conexión tenant no participa en el rollback de RefreshDatabase (conexión aparte),
        // por lo que garantizamos aislamiento de los SUM limpiando las líneas auxiliares.
        DB::connection('tenant')->table('order_auxiliary_lines')->delete();

        $result = $this->createTenantAndAdminUser('auxstats');
        $this->tenantSubdomain = $result['slug'];
        $this->token = $result['token'];
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ];
    }

    private function seedClosedOrderWithAuxLine(float $quantity, float $unitPrice, float $rate): Order
    {
        $context = $this->createSalesContext('AuxStats');
        $customer = $this->createCustomerForTest($context, 'AuxStats');
        $order = Order::factory()->finished()->create([
            'customer_id' => $customer->id,
            'payment_term_id' => $context['paymentTerm']->id,
            'salesperson_id' => $context['salesperson']->id,
            'transport_id' => $context['transport']->id,
            'load_date' => now()->format('Y-m-d'),
        ]);

        $tax = Tax::query()->firstOrCreate(['name' => "IVA {$rate}%"], ['rate' => $rate]);

        OrderAuxiliaryLine::factory()->create([
            'order_id' => $order->id,
            'auxiliary_product_id' => null,
            'description' => 'Nieve granulada',
            'quantity' => $quantity,
            'unit' => 'kg',
            'unit_price' => $unitPrice,
            'tax_id' => $tax->id,
        ]);

        return $order;
    }

    public function test_total_amount_stats(): void
    {
        // 500 kg * 0.08 = 40 subtotal; IVA 10% => 4; total 44
        $this->seedClosedOrderWithAuxLine(500, 0.08, 10);

        $from = now()->subDay()->format('Y-m-d');
        $to = now()->addDay()->format('Y-m-d');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/statistics/auxiliary-lines/total-amount?dateFrom={$from}&dateTo={$to}");

        $response->assertStatus(200);
        $response->assertJsonPath('subtotal', 40);
        $response->assertJsonPath('tax', 4);
        $response->assertJsonPath('value', 44);
    }

    public function test_by_product_ranking(): void
    {
        $this->seedClosedOrderWithAuxLine(100, 1, 0);

        $from = now()->subDay()->format('Y-m-d');
        $to = now()->addDay()->format('Y-m-d');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/statistics/auxiliary-lines/by-product?dateFrom={$from}&dateTo={$to}");

        $response->assertStatus(200);
        $response->assertJsonPath('0.name', 'Nieve granulada');
        $response->assertJsonPath('0.subtotal', 100);
    }

    public function test_orders_total_amount_with_include_auxiliary_returns_combined(): void
    {
        $this->seedClosedOrderWithAuxLine(500, 0.08, 10);

        $from = now()->subDay()->format('Y-m-d');
        $to = now()->addDay()->format('Y-m-d');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/statistics/orders/total-amount?dateFrom={$from}&dateTo={$to}&includeAuxiliary=1");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'seafood' => ['subtotal', 'tax', 'total'],
            'auxiliary' => ['subtotal', 'tax', 'total'],
            'combined' => ['subtotal', 'tax', 'total'],
        ]);
        $response->assertJsonPath('auxiliary.subtotal', 40);
    }

    public function test_chart_data(): void
    {
        $this->seedClosedOrderWithAuxLine(100, 2, 0);

        $from = now()->subDay()->format('Y-m-d');
        $to = now()->addDay()->format('Y-m-d');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/statistics/auxiliary-lines/chart-data?dateFrom={$from}&dateTo={$to}&groupBy=day");

        $response->assertStatus(200);
        $response->assertJsonStructure([['date', 'subtotal', 'total']]);
    }
}
