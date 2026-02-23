<?php

namespace Tests\Feature;

use App\Jobs\OnboardTenantJob;
use App\Models\SuperadminUser;
use App\Models\Tenant;
use App\Sanctum\SuperadminPersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class SuperadminTenantCrudTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?SuperadminUser $superadmin = null;

    private ?array $headers = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();

        $this->superadmin = SuperadminUser::create([
            'name' => 'SA Test',
            'email' => 'sa-crud-' . uniqid() . '@lapesquerapp.es',
        ]);

        $previousModel = Sanctum::$personalAccessTokenModel;
        Sanctum::usePersonalAccessTokenModel(SuperadminPersonalAccessToken::class);
        $token = $this->superadmin->createToken('test')->plainTextToken;
        Sanctum::usePersonalAccessTokenModel($previousModel);

        $this->headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    // ---------- index ----------

    public function test_index_lists_tenants(): void
    {
        Tenant::create(['name' => 'T1', 'subdomain' => 'idx-' . uniqid(), 'database' => 'db1', 'status' => 'active']);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v2/superadmin/tenants');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_index_filters_by_status(): void
    {
        Tenant::create(['name' => 'Active', 'subdomain' => 'filt-a-' . uniqid(), 'database' => 'dba', 'status' => 'active']);
        Tenant::create(['name' => 'Suspended', 'subdomain' => 'filt-s-' . uniqid(), 'database' => 'dbs', 'status' => 'suspended']);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v2/superadmin/tenants?status=suspended');

        $response->assertOk();
        foreach ($response->json('data') as $tenant) {
            $this->assertEquals('suspended', $tenant['status']);
        }
    }

    // ---------- show ----------

    public function test_show_returns_tenant_detail(): void
    {
        $tenant = Tenant::create(['name' => 'Detail', 'subdomain' => 'show-' . uniqid(), 'database' => 'dbshow', 'status' => 'active']);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/v2/superadmin/tenants/{$tenant->id}");

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Detail');
    }

    // ---------- store ----------

    public function test_store_creates_tenant_and_dispatches_onboarding(): void
    {
        Queue::fake();

        $sub = 'new-' . uniqid();

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v2/superadmin/tenants', [
                'name' => 'New Tenant',
                'subdomain' => $sub,
                'admin_email' => 'admin@newtenant.es',
                'plan' => 'basic',
                'timezone' => 'Europe/Madrid',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.subdomain', $sub);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.database', 'tenant_' . $sub);

        Queue::assertPushed(OnboardTenantJob::class);
    }

    public function test_store_rejects_duplicate_subdomain(): void
    {
        $sub = 'dup-' . uniqid();
        Tenant::create(['name' => 'Existing', 'subdomain' => $sub, 'database' => 'tenant_' . $sub, 'status' => 'active']);

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v2/superadmin/tenants', [
                'name' => 'Duplicate',
                'subdomain' => $sub,
                'admin_email' => 'admin@dup.es',
            ]);

        $response->assertUnprocessable();
    }

    public function test_store_validates_subdomain_format(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v2/superadmin/tenants', [
                'name' => 'Bad Subdomain',
                'subdomain' => 'UPPER_CASE',
                'admin_email' => 'admin@test.es',
            ]);

        $response->assertUnprocessable();
    }

    // ---------- update ----------

    public function test_update_modifies_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Old', 'subdomain' => 'upd-' . uniqid(), 'database' => 'dbupd', 'status' => 'active']);

        $response = $this->withHeaders($this->headers)
            ->putJson("/api/v2/superadmin/tenants/{$tenant->id}", [
                'name' => 'Updated Name',
                'plan' => 'pro',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.plan', 'pro');
    }

    // ---------- status changes ----------

    public function test_activate_changes_status_to_active(): void
    {
        $tenant = Tenant::create(['name' => 'Suspended', 'subdomain' => 'act-' . uniqid(), 'database' => 'dbact', 'status' => 'suspended']);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v2/superadmin/tenants/{$tenant->id}/activate");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
    }

    public function test_suspend_changes_status_to_suspended(): void
    {
        $tenant = Tenant::create(['name' => 'Active', 'subdomain' => 'sus-' . uniqid(), 'database' => 'dbsus', 'status' => 'active']);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v2/superadmin/tenants/{$tenant->id}/suspend");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'suspended');
    }

    public function test_cancel_changes_status_to_cancelled(): void
    {
        $tenant = Tenant::create(['name' => 'Active', 'subdomain' => 'can-' . uniqid(), 'database' => 'dbcan', 'status' => 'active']);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v2/superadmin/tenants/{$tenant->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
    }

    // ---------- dashboard ----------

    public function test_dashboard_returns_stats(): void
    {
        Tenant::create(['name' => 'A1', 'subdomain' => 'dash-a-' . uniqid(), 'database' => 'db', 'status' => 'active']);
        Tenant::create(['name' => 'A2', 'subdomain' => 'dash-s-' . uniqid(), 'database' => 'db', 'status' => 'suspended']);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v2/superadmin/dashboard');

        $response->assertOk();
        $response->assertJsonStructure(['total', 'active', 'suspended', 'pending', 'cancelled']);
        $this->assertGreaterThanOrEqual(1, $response->json('active'));
    }

    // ---------- unauthorized ----------

    public function test_unauthenticated_cannot_access_tenants(): void
    {
        $response = $this->getJson('/api/v2/superadmin/tenants');

        $response->assertUnauthorized();
    }
}
