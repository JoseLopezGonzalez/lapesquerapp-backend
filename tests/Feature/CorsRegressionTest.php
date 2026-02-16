<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsRegressionTest extends TestCase
{
    /**
     * Preflight OPTIONS with allowed origin returns 204 and full CORS headers.
     */
    public function test_preflight_returns_cors_headers_for_allowed_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://brisamar.lapesquerapp.es',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'X-Tenant,Content-Type,Authorization',
        ])->options('/api/v2/auth/request-access');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://brisamar.lapesquerapp.es');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertHeader('Access-Control-Allow-Methods');
    }

    /**
     * Preflight OPTIONS on public route (health) also gets CORS headers.
     */
    public function test_preflight_on_health_returns_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://pymcolorao.lapesquerapp.es',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/health');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://pymcolorao.lapesquerapp.es');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * Normal GET request with allowed origin gets CORS headers (even on error responses).
     */
    public function test_get_request_includes_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://brisamar.lapesquerapp.es',
        ])->getJson('/api/health');

        // Health may return 500 in test env (missing Redis), but CORS headers must be present
        $response->assertHeader('Access-Control-Allow-Origin', 'https://brisamar.lapesquerapp.es');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * POST error responses (missing tenant) still get CORS headers.
     */
    public function test_missing_tenant_error_keeps_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://brisamar.lapesquerapp.es',
        ])->postJson('/api/v2/auth/request-access', [
            'email' => 'test@example.com',
        ]);

        // 400 = Tenant not specified (from TenantMiddleware)
        $response->assertStatus(400);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://brisamar.lapesquerapp.es');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * Disallowed origin does NOT get Access-Control-Allow-Origin header.
     */
    public function test_disallowed_origin_does_not_get_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://malicious-site.com',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/health');

        $response->assertStatus(204);
        $this->assertNull(
            $response->headers->get('Access-Control-Allow-Origin'),
            'Disallowed origin should NOT get Access-Control-Allow-Origin'
        );
    }

    /**
     * Pattern-matched subdomain origin gets CORS headers.
     */
    public function test_pattern_matched_origin_gets_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://nuevotenant.lapesquerapp.es',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/health');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://nuevotenant.lapesquerapp.es');
    }

    /**
     * Request without Origin header does NOT get Access-Control-Allow-Origin.
     */
    public function test_no_origin_header_does_not_get_cors_headers(): void
    {
        $response = $this->call('OPTIONS', '/api/health');

        // HandleCors returns 200 (passes through) when there's no Origin header
        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 204]),
            'OPTIONS without Origin should return 200 or 204, got ' . $response->getStatusCode()
        );
        $this->assertNull(
            $response->headers->get('Access-Control-Allow-Origin'),
            'Request without Origin should not have Access-Control-Allow-Origin'
        );
    }

    /**
     * Non-API path does NOT get CORS headers.
     */
    public function test_non_api_path_does_not_get_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://brisamar.lapesquerapp.es',
        ])->get('/');

        $this->assertNull(
            $response->headers->get('Access-Control-Allow-Origin'),
            'Non-API paths should not get CORS headers'
        );
    }

    /**
     * Localhost origin is allowed in development.
     */
    public function test_localhost_origin_is_allowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/health');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }
}
