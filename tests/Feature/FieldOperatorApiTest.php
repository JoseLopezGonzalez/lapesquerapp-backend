<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\FieldOperator;
use App\Models\Salesperson;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class FieldOperatorApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private string $tenantSubdomain;
    private User $adminUser;
    private User $fieldUser;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();

        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'field-operator-' . uniqid();
        Tenant::create([
            'name' => 'Field Operator Tenant',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $this->tenantSubdomain = $slug;
        $this->adminUser = User::create([
            'name' => 'Admin',
            'email' => $slug . '-admin@test.com',
            'role' => Role::Administrador->value,
            'active' => true,
        ]);
        $this->fieldUser = User::create([
            'name' => 'Field User',
            'email' => $slug . '-field@test.com',
            'role' => Role::RepartidorAutoventa->value,
            'active' => true,
        ]);
    }

    private function headersFor(User $user): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_admin_can_create_field_operator_and_get_options(): void
    {
        $response = $this->withHeaders($this->headersFor($this->adminUser))
            ->postJson('/api/v2/field-operators', [
                'name' => 'Repartidor Uno',
                'user_id' => $this->fieldUser->id,
                'emails' => ['field@example.com'],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.userId', $this->fieldUser->id);

        $options = $this->withHeaders($this->headersFor($this->adminUser))
            ->getJson('/api/v2/field-operators/options');

        $options->assertStatus(200);
        $options->assertJsonFragment(['name' => 'Repartidor Uno']);
    }

    public function test_field_user_cannot_access_field_operator_admin_crud(): void
    {
        FieldOperator::create([
            'name' => 'Asignado',
            'user_id' => $this->fieldUser->id,
        ]);

        $response = $this->withHeaders($this->headersFor($this->fieldUser))
            ->getJson('/api/v2/field-operators');

        $response->assertStatus(403);
    }

    public function test_admin_cannot_create_field_operator_for_user_with_salesperson_identity(): void
    {
        $dualUser = User::create([
            'name' => 'Dual User',
            'email' => 'dual-' . uniqid() . '@test.com',
            'role' => Role::RepartidorAutoventa->value,
            'active' => true,
        ]);

        Salesperson::create([
            'name' => 'Comercial Dual',
            'user_id' => $dualUser->id,
        ]);

        $response = $this->withHeaders($this->headersFor($this->adminUser))
            ->postJson('/api/v2/field-operators', [
                'name' => 'Operativo Dual',
                'user_id' => $dualUser->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_admin_cannot_update_field_operator_to_user_with_salesperson_identity(): void
    {
        $fieldOperator = FieldOperator::create([
            'name' => 'Operativo Base',
            'user_id' => $this->fieldUser->id,
        ]);

        $dualUser = User::create([
            'name' => 'Dual User Update',
            'email' => 'dual-update-' . uniqid() . '@test.com',
            'role' => Role::RepartidorAutoventa->value,
            'active' => true,
        ]);

        Salesperson::create([
            'name' => 'Comercial Dual Update',
            'user_id' => $dualUser->id,
        ]);

        $response = $this->withHeaders($this->headersFor($this->adminUser))
            ->putJson('/api/v2/field-operators/' . $fieldOperator->id, [
                'user_id' => $dualUser->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }
}
