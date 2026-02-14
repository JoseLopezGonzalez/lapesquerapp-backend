<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Salesperson;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class SalespersonApiTest extends TestCase
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
        $slug = 'salesperson-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Salesperson',
            'subdomain' => $slug,
            'database' => $database,
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Test User',
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

    public function test_can_list_salespeople(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/salespeople');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_create_salesperson(): void
    {
        $name = 'Comercial Test ' . uniqid();
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/salespeople', [
                'name' => $name,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'name']]);
        $this->assertDatabaseHas('salespeople', ['name' => $name], 'tenant');
    }

    public function test_can_show_salesperson(): void
    {
        $salesperson = Salesperson::create([
            'name' => 'Comercial Show ' . uniqid(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/salespeople/' . $salesperson->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $salesperson->id);
        $response->assertJsonPath('data.name', $salesperson->name);
    }

    public function test_can_update_salesperson(): void
    {
        $salesperson = Salesperson::create([
            'name' => 'Comercial Update ' . uniqid(),
        ]);
        $newName = 'Comercial Actualizado ' . uniqid();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/salespeople/' . $salesperson->id, [
                'name' => $newName,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', $newName);
        $this->assertDatabaseHas('salespeople', ['id' => $salesperson->id, 'name' => $newName], 'tenant');
    }

    public function test_can_destroy_salesperson_without_customers_or_orders(): void
    {
        $salesperson = Salesperson::create([
            'name' => 'Comercial Destroy ' . uniqid(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/salespeople/' . $salesperson->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('salespeople', ['id' => $salesperson->id], 'tenant');
    }
}
