<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class DynamicCorsTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
    }

    // ---------- Static origins (local/testing) ----------

    public function test_localhost_origin_allowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/health');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_admin_origin_allowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://admin.lapesquerapp.es',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/health');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://admin.lapesquerapp.es');
    }

    public function test_disallowed_origin_no_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.com',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/health');

        $response->assertStatus(204);
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    // ---------- Vary header ----------

    public function test_vary_origin_header_present(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->getJson('/api/health');

        $response->assertHeader('Vary', 'Origin');
    }

    // ---------- Non-API paths ----------

    public function test_non_api_path_no_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->get('/');

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    // ---------- Cache invalidation ----------

    public function test_cors_cache_is_invalidated_on_status_change(): void
    {
        $sub = 'corscache-' . uniqid();
        $tenant = Tenant::create([
            'name' => 'CORS Test',
            'subdomain' => $sub,
            'database' => 'db_cors',
            'status' => 'active',
        ]);

        Cache::put("cors:tenant:{$sub}", true, 600);
        $this->assertTrue(Cache::has("cors:tenant:{$sub}"));

        app(\App\Services\Superadmin\TenantManagementService::class)->changeStatus($tenant, 'suspended');

        $this->assertFalse(Cache::has("cors:tenant:{$sub}"));
    }

    // ---------- Error responses keep CORS ----------

    public function test_error_response_keeps_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->postJson('/api/v2/auth/request-access', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(400);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    // ---------- Max-Age ----------

    public function test_preflight_returns_max_age(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/health');

        $response->assertHeader('Access-Control-Max-Age', '86400');
    }
}
