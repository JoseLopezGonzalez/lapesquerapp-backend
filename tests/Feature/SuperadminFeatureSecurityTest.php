<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\SuperadminUser;
use App\Models\Tenant;
use App\Sanctum\SuperadminPersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperadminFeatureSecurityTest extends TestCase
{
    use RefreshDatabase;

    private ?SuperadminUser $superadmin = null;

    private ?array $headers = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();

        $this->superadmin = SuperadminUser::create([
            'name' => 'SA Feature',
            'email' => 'sa-feature-'.uniqid().'@lapesquerapp.es',
        ]);

        $previousModel = Sanctum::$personalAccessTokenModel;
        Sanctum::usePersonalAccessTokenModel(SuperadminPersonalAccessToken::class);
        $token = $this->superadmin->createToken('test')->plainTextToken;
        Sanctum::usePersonalAccessTokenModel($previousModel);

        $this->headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    public function test_set_override_persists_feature_flag_override(): void
    {
        $tenant = Tenant::create([
            'name' => 'Feature Tenant',
            'subdomain' => 'feature-'.uniqid(),
            'database' => 'feature_db',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        FeatureFlag::create([
            'flag_key' => 'crm_enhanced_dashboard',
            'plan' => 'basic',
            'enabled' => false,
            'description' => 'CRM dashboard',
        ]);

        $response = $this->withHeaders($this->headers)
            ->putJson("/api/v2/superadmin/tenants/{$tenant->id}/feature-flags/crm_enhanced_dashboard", [
                'enabled' => true,
                'reason' => 'Activacion controlada',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.flag_key', 'crm_enhanced_dashboard');
        $response->assertJsonPath('data.enabled', true);

        $this->assertDatabaseHas('tenant_feature_overrides', [
            'tenant_id' => $tenant->id,
            'flag_key' => 'crm_enhanced_dashboard',
            'enabled' => 1,
            'overridden_by_superadmin_id' => $this->superadmin->id,
        ], 'mysql');
    }

    public function test_set_override_requires_enabled_flag(): void
    {
        $tenant = Tenant::create([
            'name' => 'Feature Validation Tenant',
            'subdomain' => 'feature-validation-'.uniqid(),
            'database' => 'feature_validation_db',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $response = $this->withHeaders($this->headers)
            ->putJson("/api/v2/superadmin/tenants/{$tenant->id}/feature-flags/crm_enhanced_dashboard", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['enabled']);
    }

    public function test_block_creates_tenant_blocklist_entry(): void
    {
        $tenant = Tenant::create([
            'name' => 'Security Tenant',
            'subdomain' => 'security-'.uniqid(),
            'database' => 'security_db',
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v2/superadmin/tenants/{$tenant->id}/block", [
                'type' => 'ip',
                'value' => '127.0.0.1',
                'reason' => 'Intentos sospechosos',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'ip');
        $response->assertJsonPath('data.value', '127.0.0.1');

        $this->assertDatabaseHas('tenant_blocklists', [
            'tenant_id' => $tenant->id,
            'type' => 'ip',
            'value' => '127.0.0.1',
            'blocked_by_superadmin_id' => $this->superadmin->id,
        ], 'mysql');
    }

    public function test_block_validates_type(): void
    {
        $tenant = Tenant::create([
            'name' => 'Security Validation Tenant',
            'subdomain' => 'security-validation-'.uniqid(),
            'database' => 'security_validation_db',
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v2/superadmin/tenants/{$tenant->id}/block", [
                'type' => 'domain',
                'value' => 'example.com',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type']);
    }
}
