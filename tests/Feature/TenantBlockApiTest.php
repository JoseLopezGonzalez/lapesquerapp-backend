<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for Tenant block: endpoint público de resolución por subdominio.
 * GET /api/v2/public/tenant/{subdomain} — sin auth, sin X-Tenant.
 */
class TenantBlockApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
    }

    public function test_returns_200_with_tenant_data_when_subdomain_exists(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        Tenant::create([
            'name' => 'Empresa Test S.L.',
            'subdomain' => 'empresa-test',
            'database' => $database,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v2/public/tenant/empresa-test');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'status' => 'active',
                    'name' => 'Empresa Test S.L.',
                ],
            ]);
    }

    public function test_returns_200_with_active_false_when_tenant_inactive(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        Tenant::create([
            'name' => 'Empresa Inactiva',
            'subdomain' => 'inactiva',
            'database' => $database,
            'status' => 'suspended',
        ]);

        $response = $this->getJson('/api/v2/public/tenant/inactiva');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'status' => 'suspended',
                    'name' => 'Empresa Inactiva',
                ],
            ]);
    }

    public function test_returns_404_with_spanish_message_when_tenant_not_found(): void
    {
        $response = $this->getJson('/api/v2/public/tenant/subdominio-inexistente');

        $response->assertNotFound()
            ->assertJson([
                'error' => 'Tenant no encontrado',
            ]);
    }

    public function test_returns_422_when_subdomain_invalid(): void
    {
        $response = $this->getJson('/api/v2/public/tenant/subdominio con espacios');

        $response->assertUnprocessable();
    }

    public function test_returns_422_when_subdomain_contains_invalid_characters(): void
    {
        $response = $this->getJson('/api/v2/public/tenant/subdominio@invalido');

        $response->assertUnprocessable();
    }
}
