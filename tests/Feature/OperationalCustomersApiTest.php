<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Country;
use App\Models\Customer;
use App\Models\FieldOperator;
use App\Models\PaymentTerm;
use App\Models\Salesperson;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OperationalCustomersApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private string $tenantSubdomain;
    private User $adminUser;
    private User $fieldUser;
    private FieldOperator $fieldOperator;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();

        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'operational-customers-' . uniqid();
        Tenant::create([
            'name' => 'Operational Customer Tenant',
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
        $this->fieldOperator = FieldOperator::create([
            'name' => 'Repartidor Test',
            'user_id' => $this->fieldUser->id,
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

    private function createCustomer(string $name, ?int $fieldOperatorId = null, ?int $salespersonId = null): Customer
    {
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado OC']);
        $country = Country::firstOrCreate(['name' => 'España OC']);
        $transport = Transport::firstOrCreate(
            ['name' => 'Transport OC'],
            ['vat_number' => 'B' . uniqid(), 'address' => 'Street', 'emails' => 'transport@example.com']
        );

        return Customer::create([
            'name' => $name,
            'vat_number' => null,
            'payment_term_id' => $paymentTerm->id,
            'billing_address' => 'B',
            'shipping_address' => 'S',
            'salesperson_id' => $salespersonId,
            'field_operator_id' => $fieldOperatorId,
            'operational_status' => $fieldOperatorId ? 'alta_operativa' : 'normal',
            'created_by_user_id' => $this->adminUser->id,
            'emails' => null,
            'contact_info' => null,
            'country_id' => $country->id,
            'transport_id' => $transport->id,
        ]);
    }

    public function test_admin_can_assign_operational_access_and_filter_ownerless_customers(): void
    {
        $salesperson = Salesperson::create(['name' => 'Comercial OC']);
        $customer = $this->createCustomer('Cliente Operativo', null, null);

        $update = $this->withHeaders($this->headersFor($this->adminUser))
            ->putJson('/api/v2/customers/' . $customer->id . '/assignment', [
                'field_operator_id' => $this->fieldOperator->id,
                'salesperson_id' => $salesperson->id,
                'operational_status' => 'alta_operativa',
            ]);

        $update->assertStatus(200);
        $update->assertJsonPath('data.fieldOperator.id', $this->fieldOperator->id);

        $ownerless = $this->createCustomer('Sin owner', $this->fieldOperator->id, null);

        $list = $this->withHeaders($this->headersFor($this->adminUser))
            ->getJson('/api/v2/customers?withoutSalesperson=1&fieldOperatorId=' . $this->fieldOperator->id . '&operationalStatus=alta_operativa');

        $list->assertStatus(200);
        $list->assertJsonFragment(['id' => $ownerless->id, 'name' => 'Sin owner']);
    }

    public function test_field_user_only_gets_own_customer_options_and_cannot_open_general_customers_crud(): void
    {
        $this->createCustomer('Asignado', $this->fieldOperator->id, null);
        $this->createCustomer('Ajeno', null, null);

        $options = $this->withHeaders($this->headersFor($this->fieldUser))
            ->getJson('/api/v2/field/customers/options');

        $options->assertStatus(200);
        $options->assertJsonFragment(['name' => 'Asignado']);
        $options->assertJsonMissing(['name' => 'Ajeno']);

        $customersIndex = $this->withHeaders($this->headersFor($this->fieldUser))
            ->getJson('/api/v2/customers');

        $customersIndex->assertStatus(403);
    }

    public function test_field_user_without_field_operator_gets_forbidden_on_customer_options(): void
    {
        $fieldUserWithoutOperator = User::create([
            'name' => 'Field User Without Operator',
            'email' => $this->tenantSubdomain . '-field-no-operator@test.com',
            'role' => Role::RepartidorAutoventa->value,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->headersFor($fieldUserWithoutOperator))
            ->getJson('/api/v2/field/customers/options');

        $response->assertStatus(403);
    }
}
