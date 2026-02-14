<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for Configuración por tenant (Settings block).
 * GET/PUT settings, Policy (solo administrador y tecnico), password masked en GET.
 */
class SettingsBlockApiTest extends TestCase
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
        $slug = 'settings-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Settings',
            'subdomain' => $slug,
            'database' => $database,
            'active' => true,
        ]);

        $this->authUser = User::create([
            'name' => 'Test Admin Settings',
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

    public function test_settings_index_returns_401_without_token(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/settings');

        $response->assertStatus(401);
    }

    public function test_settings_index_returns_403_for_role_without_permission(): void
    {
        $operario = User::create([
            'name' => 'Operario Settings',
            'email' => 'op-' . uniqid() . '@test.com',
            'role' => Role::Operario->value,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeadersForUser($operario))
            ->getJson('/api/v2/settings');

        $response->assertStatus(403);
    }

    public function test_settings_index_returns_200_and_key_value_structure(): void
    {
        Setting::query()->updateOrInsert(['key' => 'company.name'], ['value' => 'Test Company']);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/settings');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('company.name', $data);
        $this->assertSame('Test Company', $data['company.name']);
    }

    public function test_settings_index_masks_password(): void
    {
        Setting::query()->updateOrInsert(
            ['key' => Setting::SENSITIVE_KEY_PASSWORD],
            ['value' => 'secret123']
        );

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/v2/settings');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey(Setting::SENSITIVE_KEY_PASSWORD, $data);
        $this->assertSame('********', $data[Setting::SENSITIVE_KEY_PASSWORD]);
    }

    public function test_settings_update_returns_401_without_token(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->putJson('/api/v2/settings', ['company.name' => 'New Name']);

        $response->assertStatus(401);
    }

    public function test_settings_update_returns_403_for_role_without_permission(): void
    {
        $operario = User::create([
            'name' => 'Operario Update',
            'email' => 'opup-' . uniqid() . '@test.com',
            'role' => Role::Operario->value,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeadersForUser($operario))
            ->putJson('/api/v2/settings', ['company.name' => 'New Name']);

        $response->assertStatus(403);
    }

    public function test_settings_update_persists_values(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/settings', [
                'company.name' => 'Updated Company',
                'company.address.street' => 'Calle Test 1',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Settings updated']);

        $this->assertSame('Updated Company', Setting::getAllKeyValue()['company.name'] ?? null);
        $this->assertSame('Calle Test 1', Setting::getAllKeyValue()['company.address.street'] ?? null);
    }

    public function test_settings_update_does_not_clear_password_when_omitted_and_email_config_exists(): void
    {
        Setting::query()->updateOrInsert(['key' => 'company.mail.host'], ['value' => 'smtp.example.com']);
        Setting::query()->updateOrInsert(['key' => 'company.mail.username'], ['value' => 'user']);
        Setting::query()->updateOrInsert(
            ['key' => Setting::SENSITIVE_KEY_PASSWORD],
            ['value' => 'original-secret']
        );

        $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/settings', [
                'company.mail.host' => 'smtp.other.com',
                'company.mail.from_address' => 'from@test.com',
            ]);

        $all = Setting::getAllKeyValue();
        $this->assertSame('original-secret', $all[Setting::SENSITIVE_KEY_PASSWORD] ?? null);
    }
}
