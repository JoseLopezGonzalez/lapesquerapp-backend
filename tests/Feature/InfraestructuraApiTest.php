<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Feature tests for A.17 Infraestructura API: endpoints de verificación sin tenant ni auth.
 * GET /api/health, GET /api/test-cors
 */
class InfraestructuraApiTest extends TestCase
{
    public function test_health_returns_200_without_tenant(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
            ])
            ->assertJsonStructure([
                'status',
                'app',
                'env',
            ]);
    }

    public function test_test_cors_returns_200_without_tenant(): void
    {
        $response = $this->getJson('/api/test-cors');

        $response->assertOk()
            ->assertJson([
                'message' => 'CORS funciona correctamente.',
            ]);
    }
}
