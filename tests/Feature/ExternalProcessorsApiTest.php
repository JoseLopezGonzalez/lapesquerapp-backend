<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Country;
use App\Models\ExternalProcessor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class ExternalProcessorsApiTest extends TestCase
{
    use ConfiguresTenantConnection;
    use RefreshDatabase;

    private ?string $tenantSubdomain = null;

    private ?User $admin = null;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
        $this->createTenantAndAdmin();
    }

    private function createTenantAndAdmin(): void
    {
        $database = config('database.connections.'.config('database.default').'.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'processors-'.uniqid();

        Tenant::create([
            'name' => 'Test Tenant External Processors',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin External Processors',
            'email' => $slug.'@test.com',
            'role' => Role::Administrador->value,
            'active' => true,
        ]);

        $this->tenantSubdomain = $slug;
    }

    private function authHeaders(?User $user = null): array
    {
        $actor = $user ?? $this->admin;

        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer '.$actor->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_admin_can_create_external_processor(): void
    {
        $country = Country::factory()->create(['name' => 'España']);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/external-processors', [
                'name' => 'Congelados Atlántico S.L.',
                'legalName' => 'Congelados Atlántico Sociedad Limitada',
                'vatNumber' => 'B12345678',
                'sanitaryRegistrationNumber' => '12.34567/PO',
                'contactPerson' => 'María García',
                'phone' => '+34 986 000 000',
                'emails' => ['produccion@gmail.com'],
                'ccEmails' => ['admin@gmail.com'],
                'address' => 'Polígono Industrial, nave 4',
                'city' => 'Vigo',
                'postalCode' => '36201',
                'province' => 'Pontevedra',
                'countryId' => $country->id,
                'notes' => 'Transformador externo principal.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Congelados Atlántico S.L.')
            ->assertJsonPath('data.vatNumber', 'B12345678')
            ->assertJsonPath('data.sanitaryRegistrationNumber', '12.34567/PO')
            ->assertJsonPath('data.emails.0', 'produccion@gmail.com')
            ->assertJsonPath('data.ccEmails.0', 'admin@gmail.com')
            ->assertJsonPath('data.country.name', 'España')
            ->assertJsonPath('data.isActive', true);

        $this->assertDatabaseHas('external_processors', [
            'name' => 'Congelados Atlántico S.L.',
            'vat_number' => 'B12345678',
            'country_id' => $country->id,
            'is_active' => true,
        ], 'tenant');
    }

    public function test_index_filters_external_processors(): void
    {
        ExternalProcessor::factory()->create([
            'name' => 'Maquila Norte',
            'legal_name' => 'Transformaciones Norte S.L.',
            'vat_number' => 'B11111111',
            'sanitary_registration_number' => 'RG-111',
            'is_active' => true,
        ]);
        ExternalProcessor::factory()->create([
            'name' => 'Maquila Sur',
            'vat_number' => 'B22222222',
            'sanitary_registration_number' => 'RG-222',
            'is_active' => false,
        ]);

        $byName = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/external-processors?name=Norte');

        $byName->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vatNumber', 'B11111111');

        $byVat = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/external-processors?vatNumber=222');

        $byVat->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Maquila Sur');

        $active = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/external-processors?isActive=1');

        $active->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Maquila Norte');
    }

    public function test_external_processor_can_be_updated_and_nullable_fields_can_be_cleared(): void
    {
        $externalProcessor = ExternalProcessor::factory()->create([
            'name' => 'Maquila Original',
            'vat_number' => 'B33333333',
            'notes' => 'Notas iniciales',
            'emails' => "info@gmail.com;\nCC:admin@gmail.com;",
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/external-processors/'.$externalProcessor->id, [
                'name' => 'Maquila Actualizada',
                'vatNumber' => 'B33333333',
                'notes' => null,
                'emails' => [],
                'ccEmails' => [],
                'isActive' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Maquila Actualizada')
            ->assertJsonPath('data.notes', null)
            ->assertJsonPath('data.emails', [])
            ->assertJsonPath('data.isActive', false);

        $this->assertDatabaseHas('external_processors', [
            'id' => $externalProcessor->id,
            'name' => 'Maquila Actualizada',
            'notes' => null,
            'emails' => null,
            'is_active' => false,
        ], 'tenant');
    }

    public function test_external_processors_options_returns_only_active_processors(): void
    {
        ExternalProcessor::factory()->create([
            'name' => 'Activo',
            'vat_number' => 'B44444444',
            'is_active' => true,
        ]);
        ExternalProcessor::factory()->create([
            'name' => 'Inactivo',
            'vat_number' => 'B55555555',
            'is_active' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/external-processors/options');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Activo')
            ->assertJsonPath('0.vatNumber', 'B44444444');
    }

    public function test_external_processor_validation_and_unique_vat_number(): void
    {
        ExternalProcessor::factory()->create(['vat_number' => 'B66666666']);

        $missing = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/external-processors', []);

        $missing->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'vatNumber']);

        $duplicate = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/external-processors', [
                'name' => 'Duplicado',
                'vatNumber' => 'B66666666',
            ]);

        $duplicate->assertStatus(422)
            ->assertJsonValidationErrors(['vatNumber']);
    }

    public function test_external_processor_can_be_deleted_and_deleted_in_bulk(): void
    {
        $single = ExternalProcessor::factory()->create();
        $bulkOne = ExternalProcessor::factory()->create();
        $bulkTwo = ExternalProcessor::factory()->create();

        $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/external-processors/'.$single->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('external_processors', ['id' => $single->id], 'tenant');

        $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/external-processors', [
                'ids' => [$bulkOne->id, $bulkTwo->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('external_processors', ['id' => $bulkOne->id], 'tenant');
        $this->assertDatabaseMissing('external_processors', ['id' => $bulkTwo->id], 'tenant');
    }

    public function test_commercial_role_cannot_manage_external_processors(): void
    {
        $commercial = User::create([
            'name' => 'Comercial',
            'email' => 'commercial-'.uniqid().'@test.com',
            'role' => Role::Comercial->value,
            'active' => true,
        ]);

        $this->withHeaders($this->authHeaders($commercial))
            ->getJson('/api/v2/external-processors')
            ->assertStatus(403);
    }

    public function test_external_processors_require_authentication(): void
    {
        $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/external-processors')
            ->assertStatus(401);
    }
}
