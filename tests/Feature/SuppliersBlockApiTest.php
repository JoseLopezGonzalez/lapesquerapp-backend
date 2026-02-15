<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

/**
 * Feature tests for Proveedores + Liquidaciones block (Supplier CRUD, options).
 */
class SuppliersBlockApiTest extends TestCase
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
        $slug = 'suppliers-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Suppliers',
            'subdomain' => $slug,
            'database' => $database,
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Test User Suppliers',
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

    public function test_suppliers_index_returns_paginated(): void
    {
        (new SupplierSeeder)->run();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/suppliers');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_suppliers_index_filters_by_name(): void
    {
        (new SupplierSeeder)->run();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/suppliers?name=desarrollo');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_supplier_can_be_created(): void
    {
        $name = 'Proveedor Test ' . uniqid();
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/suppliers', [
                'name' => $name,
                'type' => 'raw_material',
                'contact_person' => 'Juan Pérez',
                'phone' => '+34 612 345 678',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'name', 'type']]);
        $response->assertJsonPath('data.name', $name);
    }

    public function test_supplier_store_validates_name_required(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/suppliers', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_supplier_show_returns_supplier(): void
    {
        (new SupplierSeeder)->run();
        $supplier = Supplier::on('tenant')->first();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/suppliers/{$supplier->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $supplier->id);
        $response->assertJsonPath('data.name', $supplier->name);
    }

    public function test_supplier_can_be_updated(): void
    {
        (new SupplierSeeder)->run();
        $supplier = Supplier::on('tenant')->first();
        $newName = 'Proveedor Actualizado ' . uniqid();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v2/suppliers/{$supplier->id}", [
                'name' => $newName,
                'type' => $supplier->type,
                'contact_person' => $supplier->contact_person,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', $newName);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => $newName], 'tenant');
    }

    public function test_supplier_can_be_deleted(): void
    {
        $supplier = Supplier::on('tenant')->create([
            'name' => 'Proveedor para eliminar ' . uniqid(),
            'type' => 'raw_material',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v2/suppliers/{$supplier->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id], 'tenant');
    }

    public function test_suppliers_destroy_multiple(): void
    {
        $s1 = Supplier::on('tenant')->create(['name' => 'Proveedor multi 1 ' . uniqid(), 'type' => 'raw_material']);
        $s2 = Supplier::on('tenant')->create(['name' => 'Proveedor multi 2 ' . uniqid(), 'type' => 'raw_material']);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/suppliers', ['ids' => [$s1->id, $s2->id]]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('suppliers', ['id' => $s1->id], 'tenant');
        $this->assertDatabaseMissing('suppliers', ['id' => $s2->id], 'tenant');
    }

    public function test_suppliers_destroy_multiple_validates_ids(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/suppliers', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids']);
    }

    public function test_suppliers_options_returns_list(): void
    {
        (new SupplierSeeder)->run();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/suppliers/options');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
    }

    public function test_supplier_liquidations_get_suppliers_returns_data(): void
    {
        (new SupplierSeeder)->run();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/supplier-liquidations/suppliers?dates[start]=2020-01-01&dates[end]=2030-12-31');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_supplier_liquidations_get_suppliers_validates_dates(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/supplier-liquidations/suppliers');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dates.start', 'dates.end']);
    }

    public function test_supplier_liquidations_get_details_returns_structure(): void
    {
        (new SupplierSeeder)->run();
        $supplier = Supplier::on('tenant')->first();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v2/supplier-liquidations/{$supplier->id}/details?dates[start]=2020-01-01&dates[end]=2030-12-31");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'supplier' => ['id', 'name'],
            'date_range' => ['start', 'end'],
            'receptions',
            'dispatches',
            'summary' => [
                'total_receptions',
                'total_dispatches',
                'total_receptions_weight',
                'total_receptions_amount',
            ],
        ]);
    }

    public function test_suppliers_require_authentication(): void
    {
        $response = $this->withHeaders([
            'X-Tenant' => $this->tenantSubdomain,
            'Accept' => 'application/json',
        ])->getJson('/api/v2/suppliers');

        $response->assertStatus(401);
    }
}
