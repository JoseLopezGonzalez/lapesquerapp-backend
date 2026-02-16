<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsRegressionTest extends TestCase
{
    // ─── Preflight (OPTIONS) Tests ──────────────────────────────────

    /**
     * Preflight OPTIONS with allowed production origin returns 204 + full CORS headers.
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
        $response->assertHeader('Access-Control-Max-Age', '86400');
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
        $response->assertHeader('Access-Control-Max-Age', '86400');
    }

    // ─── Actual Request Tests ───────────────────────────────────────

    /**
     * Normal GET request with allowed origin gets CORS headers.
     */
    public function test_get_request_includes_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://brisamar.lapesquerapp.es',
        ])->getJson('/api/health');

        $response->assertHeader('Access-Control-Allow-Origin', 'https://brisamar.lapesquerapp.es');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * POST error responses (missing tenant) still get CORS headers.
     * Confirms HandleCors wraps error responses from TenantMiddleware.
     */
    public function test_missing_tenant_error_keeps_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://brisamar.lapesquerapp.es',
        ])->postJson('/api/v2/auth/request-access', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(400);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://brisamar.lapesquerapp.es');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    // ─── Security Tests ─────────────────────────────────────────────

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
     * Request without Origin header does NOT get Access-Control-Allow-Origin.
     */
    public function test_no_origin_header_does_not_get_cors_headers(): void
    {
        $response = $this->call('OPTIONS', '/api/health');

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
     * Non-API path does NOT get CORS headers (paths config scopes to api/*).
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

    // ─── Origin Pattern Matching Tests ──────────────────────────────

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

    /**
     * Tenant-subdomain localhost is allowed via pattern.
     */
    public function test_tenant_subdomain_localhost_is_allowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://brisamar.localhost:3000',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/health');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://brisamar.localhost:3000');
    }

    /**
     * Bare domain lapesquerapp.es (no subdomain) is allowed as exact match.
     */
    public function test_bare_domain_is_allowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://lapesquerapp.es',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/health');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://lapesquerapp.es');
    }

    /**
     * Congeladosbrisamar subdomain is allowed via pattern.
     */
    public function test_congeladosbrisamar_subdomain_is_allowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://app.congeladosbrisamar.es',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/health');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://app.congeladosbrisamar.es');
    }

    // ─── Vary Header Test ───────────────────────────────────────────

    /**
     * Responses include Vary: Origin for proper caching behavior.
     */
    public function test_response_includes_vary_origin_header(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://brisamar.lapesquerapp.es',
        ])->getJson('/api/health');

        $vary = $response->headers->get('Vary');
        $this->assertNotNull($vary, 'Vary header should be present');
        $this->assertStringContainsString('Origin', $vary, 'Vary should include Origin');
    }
}
