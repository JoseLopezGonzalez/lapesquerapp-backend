<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CaptureZone;
use App\Models\Species;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\CaptureZonesSeeder;
use Database\Seeders\SpeciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for A.6 Production block: productions index, require auth.
 * Production is complex; this file covers flujos básicos de listado y auth.
 */
class ProductionBlockApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?string $token = null;

    private ?string $tenantSubdomain = null;

    private bool $catalogSeeded = false;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantAndUser();
    }

    private function seedCatalog(): void
    {
        if ($this->catalogSeeded) {
            return;
        }
        $this->catalogSeeded = true;
        (new CaptureZonesSeeder)->run();
        (new SpeciesSeeder)->run();
    }

    private function createTenantAndUser(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'production-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Production',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Test User Production',
            'email' => $slug . '@test.com',
            'password' => bcrypt('password'),
            'role' => Role::Administrador->value,
        ]);

        $this->token = $user->createToken('test')->plainTextToken;
        $this->tenantSubdomain = $slug;
    }

    private function authHeaders(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_productions_list_returns_paginated(): void
    {
        $this->seedCatalog();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/productions');

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_productions_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/productions');

        $response->assertUnauthorized();
    }
}
