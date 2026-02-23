<?php

namespace Tests\Feature;

use App\Models\SuperadminMagicLinkToken;
use App\Models\SuperadminUser;
use App\Sanctum\SuperadminPersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class SuperadminAuthTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
    }

    private function createSuperadmin(): SuperadminUser
    {
        return SuperadminUser::create([
            'name' => 'Test SA',
            'email' => 'sa-' . uniqid() . '@lapesquerapp.es',
        ]);
    }

    private function superadminAuthHeaders(SuperadminUser $user): array
    {
        $previousModel = Sanctum::$personalAccessTokenModel;
        Sanctum::usePersonalAccessTokenModel(SuperadminPersonalAccessToken::class);
        $token = $user->createToken('test')->plainTextToken;
        Sanctum::usePersonalAccessTokenModel($previousModel);

        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    // ---------- request-access ----------

    public function test_request_access_returns_200_for_valid_email(): void
    {
        Mail::fake();
        $user = $this->createSuperadmin();

        $response = $this->postJson('/api/v2/superadmin/auth/request-access', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message']);
    }

    public function test_request_access_returns_200_for_unknown_email(): void
    {
        $response = $this->postJson('/api/v2/superadmin/auth/request-access', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message']);
    }

    public function test_request_access_returns_422_without_email(): void
    {
        $response = $this->postJson('/api/v2/superadmin/auth/request-access', []);

        $response->assertUnprocessable();
    }

    // ---------- verify-magic-link ----------

    public function test_verify_magic_link_returns_token(): void
    {
        $user = $this->createSuperadmin();
        $rawToken = 'testtoken1234567890abcdef1234567890abcdef1234567890abcdef12345678';

        SuperadminMagicLinkToken::create([
            'email' => $user->email,
            'token' => hash('sha256', $rawToken),
            'type' => 'magic_link',
            'expires_at' => now('UTC')->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v2/superadmin/auth/verify-magic-link', [
            'token' => $rawToken,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['access_token', 'token_type', 'user']);
    }

    public function test_verify_magic_link_rejects_invalid_token(): void
    {
        $response = $this->postJson('/api/v2/superadmin/auth/verify-magic-link', [
            'token' => 'invalidtoken',
        ]);

        $response->assertStatus(400);
    }

    // ---------- verify-otp ----------

    public function test_verify_otp_returns_token(): void
    {
        $user = $this->createSuperadmin();
        $code = '123456';

        SuperadminMagicLinkToken::create([
            'email' => $user->email,
            'token' => hash('sha256', $code . $user->email . now('UTC')->timestamp),
            'type' => 'otp',
            'otp_code' => $code,
            'expires_at' => now('UTC')->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v2/superadmin/auth/verify-otp', [
            'email' => $user->email,
            'code' => $code,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['access_token', 'token_type', 'user']);
    }

    public function test_verify_otp_rejects_wrong_code(): void
    {
        $response = $this->postJson('/api/v2/superadmin/auth/verify-otp', [
            'email' => 'test@test.com',
            'code' => '000000',
        ]);

        $response->assertStatus(400);
    }

    // ---------- me ----------

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->withHeaders($this->superadminAuthHeaders($user))
            ->getJson('/api/v2/superadmin/auth/me');

        $response->assertOk();
        $response->assertJsonPath('data.email', $user->email);
    }

    public function test_me_rejects_unauthenticated(): void
    {
        $response = $this->getJson('/api/v2/superadmin/auth/me');

        $response->assertUnauthorized();
    }

    // ---------- logout ----------

    public function test_logout_revokes_token(): void
    {
        $user = $this->createSuperadmin();
        $headers = $this->superadminAuthHeaders($user);

        $tokenCount = \App\Sanctum\SuperadminPersonalAccessToken::where('tokenable_id', $user->id)->count();
        $this->assertEquals(1, $tokenCount);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v2/superadmin/auth/logout');

        $response->assertOk();

        $tokenCount = \App\Sanctum\SuperadminPersonalAccessToken::where('tokenable_id', $user->id)->count();
        $this->assertEquals(0, $tokenCount, 'Token should be deleted after logout');
    }

    // ---------- guard isolation ----------

    public function test_tenant_token_cannot_access_superadmin_routes(): void
    {
        $this->setUpTenantConnection();

        $tenantUser = \App\Models\User::create([
            'name' => 'Tenant User',
            'email' => 'tenantuser-' . uniqid() . '@test.com',
            'role' => 'administrador',
            'active' => true,
        ]);
        $token = $tenantUser->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/superadmin/auth/me');

        $response->assertUnauthorized();
    }
}
