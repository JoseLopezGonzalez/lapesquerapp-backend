<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Sanctum\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for Auth block: login flow, me, logout, User CRUD, Session, ActivityLog.
 * Uses tenant + Sanctum auth. Policies: Session (viewAny/delete), ActivityLog (viewAny), User (delete refined).
 */
class AuthBlockApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private ?string $token = null;

    private ?string $tenantSubdomain = null;

    private ?User $authUser = null;

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
        $slug = 'auth-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Auth',
            'subdomain' => $slug,
            'database' => $database,
            'active' => true,
        ]);

        $this->authUser = User::create([
            'name' => 'Test Admin Auth',
            'email' => $slug . '@test.com',
            'role' => Role::Administrador->value,
            'active' => true,
        ]);

        $this->token = $this->authUser->createToken('test')->plainTextToken;
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

    private function authHeadersForUser(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    // ---------- Auth (public or sanctum) ----------

    public function test_auth_request_access_returns_422_without_email(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->postJson('/api/v2/auth/request-access', []);

        $response->assertStatus(422);
    }

    public function test_auth_request_access_returns_200_with_valid_email(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->postJson('/api/v2/auth/request-access', [
            'email' => 'someone@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message']);
    }

    public function test_auth_me_returns_401_without_token(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/me');

        $response->assertStatus(401);
    }

    public function test_auth_me_returns_user_when_authenticated(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/me');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id', 'name', 'email', 'role', 'active',
            'assigned_store_id', 'company_name', 'company_logo_url',
            'created_at', 'updated_at',
        ]);
        $this->assertEquals($this->authUser->email, $response->json('email'));
    }

    public function test_auth_logout_returns_200_when_authenticated(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v2/logout');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Sesión cerrada correctamente']);
    }

    public function test_auth_verify_magic_link_returns_400_for_invalid_token(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->postJson('/api/v2/auth/magic-link/verify', [
            'token' => 'invalid-token-12345',
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    }

    public function test_auth_verify_otp_returns_400_for_invalid_code(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->postJson('/api/v2/auth/otp/verify', [
            'email' => $this->authUser->email,
            'code' => '000000',
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
    }

    // ---------- Users ----------

    public function test_users_list_returns_401_without_token(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/users');

        $response->assertStatus(401);
    }

    public function test_users_list_returns_paginated_with_auth(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/users');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_users_can_be_created_with_auth(): void
    {
        $email = 'newuser-' . uniqid() . '@test.com';
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/users', [
                'name' => 'New User',
                'email' => $email,
                'role' => Role::Comercial->value,
                'active' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'name', 'email', 'role']]);
        $response->assertJsonPath('data.email', $email);
    }

    public function test_users_show_returns_200_with_auth(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/users/' . $this->authUser->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $this->authUser->id);
    }

    public function test_users_update_returns_200_with_auth(): void
    {
        $other = User::create([
            'name' => 'Other User',
            'email' => 'other-' . uniqid() . '@test.com',
            'role' => Role::Operario->value,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/users/' . $other->id, [
                'name' => 'Other User Updated',
            ]);

        $response->assertStatus(200);
        $other->refresh();
        $this->assertSame('Other User Updated', $other->name);
    }

    public function test_users_destroy_returns_403_when_deleting_self(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/users/' . $this->authUser->id);

        $response->assertStatus(403);
        $this->authUser->refresh();
        $this->assertNull($this->authUser->deleted_at);
    }

    public function test_users_destroy_returns_200_when_deleting_other_as_admin(): void
    {
        $other = User::create([
            'name' => 'To Delete',
            'email' => 'todelete-' . uniqid() . '@test.com',
            'role' => Role::Operario->value,
            'active' => true,
        ]);
        $id = $other->id;

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/users/' . $id);

        $response->assertStatus(200);
        $trashed = User::withTrashed()->find($id);
        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);
    }

    // ---------- Sessions ----------

    public function test_sessions_index_returns_401_without_token(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/sessions');

        $response->assertStatus(401);
    }

    public function test_sessions_index_returns_200_for_allowed_role(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/sessions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_sessions_index_returns_403_for_role_without_permission(): void
    {
        $operario = User::create([
            'name' => 'Operario User',
            'email' => 'operario-' . uniqid() . '@test.com',
            'role' => Role::Operario->value,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeadersForUser($operario))
            ->getJson('/api/v2/sessions');

        $response->assertStatus(403);
    }

    public function test_sessions_destroy_returns_200_for_own_token(): void
    {
        $user = User::create([
            'name' => 'Session User',
            'email' => 'session-' . uniqid() . '@test.com',
            'role' => Role::Administrador->value,
            'active' => true,
        ]);
        $token = $user->createToken('other-device')->plainTextToken;
        $accessToken = $user->tokens()->where('name', 'other-device')->first();
        $this->assertNotNull($accessToken);

        $response = $this->withHeaders($this->authHeadersForUser($user))
            ->deleteJson('/api/v2/sessions/' . $accessToken->id);

        $response->assertStatus(200);
        $this->assertNull(PersonalAccessToken::find($accessToken->id));
    }

    // ---------- Activity logs ----------

    public function test_activity_logs_index_returns_401_without_token(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/activity-logs');

        $response->assertStatus(401);
    }

    public function test_activity_logs_index_returns_200_for_allowed_role(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/activity-logs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_activity_logs_index_returns_403_for_role_without_permission(): void
    {
        $operario = User::create([
            'name' => 'Operario Logs',
            'email' => 'oplogs-' . uniqid() . '@test.com',
            'role' => Role::Operario->value,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeadersForUser($operario))
            ->getJson('/api/v2/activity-logs');

        $response->assertStatus(403);
    }
}
