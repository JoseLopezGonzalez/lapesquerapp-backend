<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Label;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class LabelApiTest extends TestCase
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
        $slug = 'label-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant',
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

    public function test_can_list_labels(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/labels');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_can_get_labels_options(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/labels/options');

        $response->assertStatus(200);
        $response->assertJsonStructure([]);
        $this->assertIsArray($response->json());
    }

    public function test_can_create_label(): void
    {
        $name = 'Etiqueta Test ' . uniqid();
        $format = ['width' => 50, 'height' => 30];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/labels', [
                'name' => $name,
                'format' => $format,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'name', 'format']]);
        $response->assertJsonPath('data.name', $name);
        $response->assertJsonPath('data.format', $format);
        $this->assertDatabaseHas('labels', ['name' => $name], 'tenant');
    }

    public function test_can_show_label(): void
    {
        $label = Label::create([
            'name' => 'Etiqueta Show ' . uniqid(),
            'format' => ['test' => true],
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/labels/' . $label->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $label->id);
        $response->assertJsonPath('data.name', $label->name);
    }

    public function test_can_update_label(): void
    {
        $label = Label::create([
            'name' => 'Etiqueta Update ' . uniqid(),
            'format' => [],
        ]);
        $newName = 'Etiqueta Actualizada ' . uniqid();
        $newFormat = ['width' => 100];

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/labels/' . $label->id, [
                'name' => $newName,
                'format' => $newFormat,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', $newName);
        $response->assertJsonPath('data.format', $newFormat);
        $this->assertDatabaseHas('labels', ['id' => $label->id, 'name' => $newName], 'tenant');
    }

    public function test_can_destroy_label(): void
    {
        $label = Label::create([
            'name' => 'Etiqueta Destroy ' . uniqid(),
            'format' => [],
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/labels/' . $label->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('labels', ['id' => $label->id], 'tenant');
    }

    public function test_can_duplicate_label(): void
    {
        $label = Label::create([
            'name' => 'Etiqueta Original ' . uniqid(),
            'format' => ['custom' => 'config'],
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/labels/' . $label->id . '/duplicate');

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'name', 'format']]);
        $response->assertJsonPath('data.name', $label->name . ' (Copia)');
        $response->assertJsonPath('data.format', $label->format);

        $newId = $response->json('data.id');
        $this->assertNotEquals($label->id, $newId);
        $this->assertDatabaseHas('labels', ['id' => $newId, 'name' => $label->name . ' (Copia)'], 'tenant');
    }

    public function test_duplicate_with_custom_name(): void
    {
        $label = Label::create([
            'name' => 'Etiqueta Duplicar ' . uniqid(),
            'format' => [],
        ]);
        $customName = 'Copia personalizada ' . uniqid();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/labels/' . $label->id . '/duplicate', [
                'name' => $customName,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', $customName);
        $this->assertDatabaseHas('labels', ['name' => $customName], 'tenant');
    }

    public function test_validation_rejects_duplicate_name_on_store(): void
    {
        $name = 'Etiqueta Unica ' . uniqid();
        Label::create(['name' => $name, 'format' => []]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/labels', [
                'name' => $name,
                'format' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_validation_requires_name_on_store(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/labels', [
                'format' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }
}
