<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\FieldOperator;
use App\Models\Salesperson;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOperationsScenario;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class CustomerCommercialPermissionsApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;
    use BuildsOperationsScenario;

    private string $tenantSubdomain;
    private User $commercialUser;
    private string $commercialToken;
    private Salesperson $commercialSalesperson;

    private Salesperson $otherSalesperson;
    private FieldOperator $fieldOperator;
    private array $salesContext;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();

        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'commercial-customer-' . uniqid();
        Tenant::create([
            'name' => 'Test Tenant Commercial Customer',
            'subdomain' => $slug,
            'database' => $database,
            'status' => 'active',
        ]);
        $this->tenantSubdomain = $slug;

        $this->commercialUser = User::create([
            'name' => 'Comercial',
            'email' => $slug . '-comercial@test.com',
            'role' => Role::Comercial->value,
            'active' => true,
        ]);
        $this->commercialToken = $this->commercialUser->createToken('test')->plainTextToken;

        $this->commercialSalesperson = Salesperson::create([
            'name' => 'Salesperson Comercial ' . uniqid(),
            'user_id' => $this->commercialUser->id,
        ]);

        $this->otherSalesperson = Salesperson::create([
            'name' => 'Salesperson Otro ' . uniqid(),
        ]);

        $this->fieldOperator = FieldOperator::create([
            'name' => 'Repartidor ' . uniqid(),
        ]);

        $this->salesContext = $this->createSalesContext('CommercialPermissions');
    }

    private function headersForCommercial(): array
    {
        return [
            'X-Tenant' => $this->tenantSubdomain,
            'Authorization' => 'Bearer ' . $this->commercialToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_commercial_can_update_own_customer(): void
    {
        $ctx = $this->salesContext;
        $customer = Customer::factory()->create([
            'salesperson_id' => $this->commercialSalesperson->id,
            'payment_term_id' => $ctx['paymentTerm']->id,
            'country_id' => $ctx['country']->id,
            'transport_id' => $ctx['transport']->id,
        ]);

        $newName = 'Cliente Comercial Update ' . uniqid();

        $response = $this->withHeaders($this->headersForCommercial())
            ->putJson('/api/v2/customers/' . $customer->id, [
                'name' => $newName,
                'vatNumber' => 'B' . uniqid(),
                'billing_address' => 'Dir fact',
                'shipping_address' => 'Dir env',
                'transportation_notes' => 'Notas transporte',
                'production_notes' => 'Notas produccion',
                'accounting_notes' => 'Notas contabilidad',
                'emails' => ['noreply@github.com'],
                'contact_info' => 'Contacto',
                'country_id' => $ctx['country']->id,
                'payment_term_id' => $ctx['paymentTerm']->id,
                'transport_id' => $ctx['transport']->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => $newName], 'tenant');
    }

    public function test_commercial_cannot_update_other_salesperson_customer(): void
    {
        $ctx = $this->salesContext;
        $customer = Customer::factory()->create([
            'salesperson_id' => $this->otherSalesperson->id,
            'payment_term_id' => $ctx['paymentTerm']->id,
            'country_id' => $ctx['country']->id,
            'transport_id' => $ctx['transport']->id,
        ]);

        $response = $this->withHeaders($this->headersForCommercial())
            ->putJson('/api/v2/customers/' . $customer->id, [
                'name' => 'Intento Update ' . uniqid(),
            ]);

        $response->assertStatus(403);
    }

    public function test_commercial_cannot_change_salesperson_id_on_update_even_if_sent(): void
    {
        $ctx = $this->salesContext;
        $customer = Customer::factory()->create([
            'salesperson_id' => $this->commercialSalesperson->id,
            'payment_term_id' => $ctx['paymentTerm']->id,
            'country_id' => $ctx['country']->id,
            'transport_id' => $ctx['transport']->id,
        ]);

        $response = $this->withHeaders($this->headersForCommercial())
            ->putJson('/api/v2/customers/' . $customer->id, [
                'name' => $customer->name,
                'vatNumber' => $customer->vat_number,
                'billing_address' => $customer->billing_address,
                'shipping_address' => $customer->shipping_address,
                'contact_info' => $customer->contact_info,
                'payment_term_id' => $customer->payment_term_id,
                'salesperson_id' => $this->otherSalesperson->id,
                'country_id' => $customer->country_id,
                'transport_id' => $customer->transport_id,
                'emails' => ['noreply@github.com'],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'salesperson_id' => $this->commercialSalesperson->id], 'tenant');
    }

    public function test_commercial_can_update_assignment_but_cannot_change_salesperson_id(): void
    {
        $ctx = $this->salesContext;
        $customer = Customer::factory()->create([
            'salesperson_id' => $this->commercialSalesperson->id,
            'payment_term_id' => $ctx['paymentTerm']->id,
            'country_id' => $ctx['country']->id,
            'transport_id' => $ctx['transport']->id,
        ]);

        $response = $this->withHeaders($this->headersForCommercial())
            ->putJson('/api/v2/customers/' . $customer->id . '/assignment', [
                'field_operator_id' => $this->fieldOperator->id,
                'operational_status' => 'alta_operativa',
                'salesperson_id' => $this->otherSalesperson->id,
            ]);

        $response->assertStatus(200);

        $customer->refresh();
        $this->assertSame($this->commercialSalesperson->id, $customer->salesperson_id);
        $this->assertSame($this->fieldOperator->id, $customer->field_operator_id);
        $this->assertSame('alta_operativa', $customer->operational_status);
    }
}

