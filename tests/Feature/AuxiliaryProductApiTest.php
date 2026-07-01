<?php

namespace Tests\Feature;

use App\Models\AuxiliaryProduct;
use App\Models\OrderAuxiliaryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class AuxiliaryProductApiTest extends TestCase
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

        $result = $this->createTenantAndAdminUser('auxprod');
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

    public function test_can_list_auxiliary_products(): void
    {
        AuxiliaryProduct::factory()->count(3)->create();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/auxiliary-products');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_list_requires_auth(): void
    {
        $response = $this->withHeader('X-Tenant', $this->tenantSubdomain)
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/v2/auxiliary-products');

        $response->assertStatus(401);
    }

    public function test_can_create_auxiliary_product(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/auxiliary-products', [
                'name' => 'Nieve granulada',
                'reference' => 'NIE-01',
                'unit' => 'kg',
                'defaultPrice' => 0.08,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Nieve granulada');
        $response->assertJsonPath('data.unit', 'kg');

        $this->assertDatabaseHas('auxiliary_products', [
            'name' => 'Nieve granulada',
            'reference' => 'NIE-01',
        ], 'tenant');
    }

    public function test_create_requires_name_and_unit(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/auxiliary-products', [
                'reference' => 'X',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'unit']);
    }

    public function test_create_rejects_duplicate_name(): void
    {
        AuxiliaryProduct::factory()->create(['name' => 'Tarrina 500g']);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/auxiliary-products', [
                'name' => 'Tarrina 500g',
                'unit' => 'ud',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_auxiliary_product(): void
    {
        $product = AuxiliaryProduct::factory()->create(['name' => 'Palet madera', 'unit' => 'ud']);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson('/api/v2/auxiliary-products/'.$product->id, [
                'defaultPrice' => 8.5,
                'unit' => 'palet',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.unit', 'palet');

        $this->assertDatabaseHas('auxiliary_products', [
            'id' => $product->id,
            'unit' => 'palet',
        ], 'tenant');
    }

    public function test_can_delete_unused_auxiliary_product(): void
    {
        $product = AuxiliaryProduct::factory()->create();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/auxiliary-products/'.$product->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('auxiliary_products', ['id' => $product->id], 'tenant');
    }

    public function test_cannot_delete_auxiliary_product_in_use(): void
    {
        $context = $this->createSalesContext('AuxDel');
        $customer = $this->createCustomerForTest($context, 'AuxDel');
        $order = $this->createOrderForTest($customer, $context);

        $product = AuxiliaryProduct::factory()->create();
        OrderAuxiliaryLine::factory()->create([
            'order_id' => $order->id,
            'auxiliary_product_id' => $product->id,
            'description' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/auxiliary-products/'.$product->id);

        $response->assertStatus(400);
        $this->assertDatabaseHas('auxiliary_products', ['id' => $product->id], 'tenant');
    }

    public function test_options_returns_only_active(): void
    {
        AuxiliaryProduct::factory()->create(['name' => 'Activo A', 'active' => true]);
        AuxiliaryProduct::factory()->inactive()->create(['name' => 'Inactivo B']);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/auxiliary-products/options');

        $response->assertStatus(200);
        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Activo A'));
        $this->assertFalse($names->contains('Inactivo B'));
    }
}
