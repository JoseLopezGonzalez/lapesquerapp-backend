<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsRegressionTest extends TestCase
{
    public function test_options_preflight_on_public_tenant_route_has_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://brisamar.lapesquerapp.es',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'X-Tenant,Content-Type,Authorization',
        ])->options('/api/v2/public/tenant/brisamar');

        $response->assertStatus(204);
        $this->assertTrue(
            $response->headers->has('Access-Control-Allow-Origin'),
            'Falta Access-Control-Allow-Origin en preflight OPTIONS'
        );
    }

    public function test_missing_tenant_error_keeps_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://brisamar.lapesquerapp.es',
        ])->postJson('/api/v2/auth/request-access', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(400);
        $this->assertTrue(
            $response->headers->has('Access-Control-Allow-Origin'),
            'Falta Access-Control-Allow-Origin en respuesta de error de TenantMiddleware'
        );
    }

    public function test_test_cors_endpoint_relies_on_middleware_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://brisamar.lapesquerapp.es',
        ])->getJson('/api/test-cors');

        $response->assertOk();
        $this->assertTrue(
            $response->headers->has('Access-Control-Allow-Origin'),
            'Falta Access-Control-Allow-Origin en /api/test-cors'
        );
    }
}
