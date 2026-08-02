<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Regresión de contrato: GET/POST/PUT /v2/orders/{orderId}/incident deben devolver la misma
 * forma de campos (camelCase, vía Incident::toArrayAssoc()). Antes de esta corrección, GET
 * devolvía el modelo Eloquent crudo (snake_case) mientras que POST/PUT devolvían toArrayAssoc()
 * (camelCase) — ver API_CONTRACT_AUDIT.md §6/§7 y docs/api-contract.md.
 */
class IncidentApiTest extends TestCase
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
        $result = $this->createTenantAndAdminUser('incident');
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

    private function createOrder(): Order
    {
        $context = $this->createSalesContext('Incident');
        $customer = $this->createCustomerForTest($context, 'Incident');

        return $this->createOrderForTest($customer, $context);
    }

    private const EXPECTED_KEYS = [
        'id', 'description', 'status', 'resolutionType', 'resolutionNotes',
        'resolvedAt', 'createdAt', 'updatedAt',
    ];

    public function test_incident_show_and_store_return_the_same_field_shape(): void
    {
        $order = $this->createOrder();

        $storeResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v2/orders/{$order->id}/incident", [
                'description' => 'Producto dañado en transporte',
            ]);

        $storeResponse->assertStatus(201)->assertJsonStructure(self::EXPECTED_KEYS);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/orders/{$order->id}/incident");

        $showResponse->assertStatus(200)->assertJsonStructure(self::EXPECTED_KEYS);

        // Mismas claves top-level en ambas respuestas (independientemente del verbo).
        $this->assertEqualsCanonicalizing(
            array_keys($storeResponse->json()),
            array_keys($showResponse->json())
        );

        // No debe haber snake_case residual del modelo Eloquent crudo (regresión del bug original).
        $showResponse->assertJsonMissingPath('order_id');
        $showResponse->assertJsonMissingPath('resolution_type');
    }
}
