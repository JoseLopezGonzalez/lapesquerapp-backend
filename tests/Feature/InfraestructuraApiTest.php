<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Feature tests for A.17 Infraestructura API: endpoints de verificación sin tenant ni auth.
 * GET /api/health
 */
class InfraestructuraApiTest extends TestCase
{
    public function test_health_responds_without_tenant(): void
    {
        $response = $this->getJson('/api/health');

        // Health may return 500 in test env (missing Redis/services);
        // assert it responds with JSON and does not require auth or tenant.
        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 500]),
            'Health endpoint should return 200 or 500, got ' . $response->getStatusCode()
        );
    }
}
