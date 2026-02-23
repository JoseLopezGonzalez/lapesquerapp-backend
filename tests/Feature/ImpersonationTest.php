<?php

namespace Tests\Feature;

use App\Models\ImpersonationLog;
use App\Models\ImpersonationRequest;
use App\Models\SuperadminUser;
use App\Models\Tenant;
use App\Models\User;
use App\Sanctum\SuperadminPersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?SuperadminUser $superadmin = null;

    private ?array $headers = null;

    private ?Tenant $tenant = null;

    private ?User $tenantUser = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->setUpTestData();
    }

    private function setUpTestData(): void
    {
        $this->superadmin = SuperadminUser::create([
            'name' => 'SA Imp',
            'email' => 'sa-imp-' . uniqid() . '@lapesquerapp.es',
        ]);

        $previousModel = Sanctum::$personalAccessTokenModel;
        Sanctum::usePersonalAccessTokenModel(SuperadminPersonalAccessToken::class);
        $token = $this->superadmin->createToken('test')->plainTextToken;
        Sanctum::usePersonalAccessTokenModel($previousModel);

        $this->headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];

        $database = config('database.connections.' . config('database.default') . '.database') ?? 'testing';
        $slug = 'imp-' . uniqid();
        $this->tenant = Tenant::create([
            'name' => 'Imp Tenant',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $this->tenantUser = User::create([
            'name' => 'Tenant Admin',
            'email' => 'admin-' . uniqid() . '@imp.es',
            'role' => 'administrador',
            'active' => true,
        ]);
    }

    // ---------- Silent impersonation ----------

    public function test_silent_impersonation_returns_token_and_logs(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v2/superadmin/tenants/{$this->tenant->id}/impersonate/silent", [
                'target_user_id' => $this->tenantUser->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['impersonation_token', 'redirect_url', 'log_id']);

        $this->assertDatabaseHas('impersonation_logs', [
            'superadmin_user_id' => $this->superadmin->id,
            'tenant_id' => $this->tenant->id,
            'target_user_id' => $this->tenantUser->id,
            'mode' => 'silent',
        ], 'mysql');
    }

    // ---------- Consent request ----------

    public function test_consent_request_creates_request_and_sends_email(): void
    {
        Mail::fake();

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v2/superadmin/tenants/{$this->tenant->id}/impersonate/request", [
                'target_user_id' => $this->tenantUser->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'request_id']);

        $this->assertDatabaseHas('impersonation_requests', [
            'superadmin_user_id' => $this->superadmin->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ], 'mysql');

        Mail::assertSent(\App\Mail\ImpersonationRequestEmail::class);
    }

    // ---------- End session ----------

    public function test_end_session_sets_ended_at(): void
    {
        $log = ImpersonationLog::create([
            'superadmin_user_id' => $this->superadmin->id,
            'tenant_id' => $this->tenant->id,
            'target_user_id' => $this->tenantUser->id,
            'mode' => 'silent',
            'started_at' => now('UTC'),
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v2/superadmin/impersonate/end', [
                'log_id' => $log->id,
            ]);

        $response->assertOk();
        $this->assertNotNull($log->fresh()->ended_at);
    }

    // ---------- Unauthenticated ----------

    public function test_unauthenticated_cannot_impersonate(): void
    {
        $response = $this->postJson("/api/v2/superadmin/tenants/{$this->tenant->id}/impersonate/silent", [
            'target_user_id' => 1,
        ]);

        $response->assertUnauthorized();
    }
}
