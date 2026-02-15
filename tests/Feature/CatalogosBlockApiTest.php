<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Country;
use App\Models\FishingGear;
use App\Models\Incoterm;
use App\Models\PaymentTerm;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for A.8 Catálogos transaccionales: Transport, Incoterm, PaymentTerm, Country, Tax, FishingGear.
 */
class CatalogosBlockApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?string $token = null;
    private ?string $tenantSubdomain = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantAndUser();
    }

    private function createTenantAndUser(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'catalogos-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Catalogos',
            'subdomain' => $slug,
            'database' => $database,
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Test User Catalogos',
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

    // --- Transport ---

    public function test_transports_list_returns_paginated(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/transports');
        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_transports_store_creates_transport(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v2/transports', [
            'name' => 'Trans Test S.L.',
            'vatNumber' => 'B12345678',
            'address' => 'Calle Falsa 123, 28001 Madrid',
            'emails' => ['trans@test.com'],
            'ccEmails' => [],
        ]);
        $response->assertCreated()->assertJsonPath('data.name', 'Trans Test S.L.');
    }

    public function test_transports_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/transports');
        $response->assertUnauthorized();
    }

    // --- Incoterm ---

    public function test_incoterms_list_returns_paginated(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/incoterms');
        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_incoterms_store_creates_incoterm(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v2/incoterms', [
            'code' => 'FOB',
            'description' => 'Free on Board',
        ]);
        $response->assertCreated()->assertJsonPath('data.code', 'FOB');
    }

    public function test_incoterms_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/incoterms');
        $response->assertUnauthorized();
    }

    // --- PaymentTerm ---

    public function test_payment_terms_list_returns_paginated(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/payment-terms');
        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_payment_terms_store_creates_payment_term(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v2/payment-terms', [
            'name' => 'Contado 30 días',
        ]);
        $response->assertCreated()->assertJsonPath('data.name', 'Contado 30 días');
    }

    public function test_payment_terms_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/payment-terms');
        $response->assertUnauthorized();
    }

    // --- Country ---

    public function test_countries_list_returns_paginated(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/countries');
        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_countries_store_creates_country(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v2/countries', [
            'name' => 'España',
        ]);
        $response->assertCreated()->assertJsonPath('data.name', 'España');
    }

    public function test_countries_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/countries');
        $response->assertUnauthorized();
    }

    // --- Tax ---

    public function test_taxes_options_returns_list(): void
    {
        Tax::create(['name' => 'IVA 21%', 'rate' => 21]);
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/taxes/options');
        $response->assertOk()->assertJsonStructure(['0' => ['id', 'name', 'rate']]);
    }

    public function test_taxes_options_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/taxes/options');
        $response->assertUnauthorized();
    }

    // --- FishingGear ---

    public function test_fishing_gears_list_returns_paginated(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/fishing-gears');
        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_fishing_gears_store_creates_fishing_gear(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v2/fishing-gears', [
            'name' => 'Cerco',
        ]);
        $response->assertCreated()->assertJsonPath('data.name', 'Cerco');
    }

    public function test_fishing_gears_require_authentication(): void
    {
        $response = $this->withHeaders(['X-Tenant' => $this->tenantSubdomain, 'Accept' => 'application/json'])
            ->getJson('/api/v2/fishing-gears');
        $response->assertUnauthorized();
    }
}
