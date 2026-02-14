<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PaymentTerm;
use App\Models\Salesperson;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class CustomerApiTest extends TestCase
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
        $slug = 'customer-' . uniqid();
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

    public function test_can_list_customers(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/customers');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_create_customer(): void
    {
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado']);
        $salesperson = Salesperson::firstOrCreate(['name' => 'Comercial Test']);
        $country = Country::firstOrCreate(['name' => 'España']);
        $transport = Transport::firstOrCreate(
            ['name' => 'Transporte Test Customer'],
            ['vat_number' => 'B' . uniqid(), 'address' => 'Calle Test', 'emails' => 't@test.com']
        );
        $name = 'Cliente Test ' . uniqid();
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v2/customers', [
                'name' => $name,
                'vatNumber' => 'B' . uniqid(),
                'billing_address' => 'Dir fact test',
                'shipping_address' => 'Dir env test',
                'payment_term_id' => $paymentTerm->id,
                'salesperson_id' => $salesperson->id,
                'country_id' => $country->id,
                'transport_id' => $transport->id,
                'emails' => ['noreply@github.com'],
                'contact_info' => 'Contacto test',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'name']]);
        $this->assertDatabaseHas('customers', ['name' => $name], 'tenant');
    }

    public function test_can_show_customer(): void
    {
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado']);
        $salesperson = Salesperson::firstOrCreate(['name' => 'Comercial Test']);
        $country = Country::firstOrCreate(['name' => 'España']);
        $transport = Transport::firstOrCreate(
            ['name' => 'Transporte Test Customer'],
            ['vat_number' => 'B' . uniqid(), 'address' => 'Calle Test', 'emails' => 't@test.com']
        );
        $customer = Customer::create([
            'name' => 'Cliente Show ' . uniqid(),
            'vat_number' => 'B' . uniqid(),
            'billing_address' => 'Dir fact',
            'shipping_address' => 'Dir env',
            'payment_term_id' => $paymentTerm->id,
            'salesperson_id' => $salesperson->id,
            'country_id' => $country->id,
            'transport_id' => $transport->id,
            'emails' => 'c@test.com',
            'contact_info' => 'Contact',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v2/customers/' . $customer->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $customer->id);
        $response->assertJsonPath('data.name', $customer->name);
    }

    public function test_can_update_customer(): void
    {
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado']);
        $salesperson = Salesperson::firstOrCreate(['name' => 'Comercial Test']);
        $country = Country::firstOrCreate(['name' => 'España']);
        $transport = Transport::firstOrCreate(
            ['name' => 'Transporte Test Customer'],
            ['vat_number' => 'B' . uniqid(), 'address' => 'Calle Test', 'emails' => 't@test.com']
        );
        $customer = Customer::create([
            'name' => 'Cliente Update ' . uniqid(),
            'vat_number' => 'B' . uniqid(),
            'billing_address' => 'Dir fact',
            'shipping_address' => 'Dir env',
            'payment_term_id' => $paymentTerm->id,
            'salesperson_id' => $salesperson->id,
            'country_id' => $country->id,
            'transport_id' => $transport->id,
            'emails' => 'c@test.com',
            'contact_info' => 'Contact',
        ]);
        $newName = 'Cliente Actualizado ' . uniqid();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v2/customers/' . $customer->id, [
                'name' => $newName,
                'vatNumber' => $customer->vat_number,
                'billing_address' => $customer->billing_address,
                'shipping_address' => $customer->shipping_address,
                'contact_info' => $customer->contact_info,
                'payment_term_id' => $customer->payment_term_id,
                'salesperson_id' => $customer->salesperson_id,
                'country_id' => $customer->country_id,
                'transport_id' => $customer->transport_id,
                'emails' => ['noreply@github.com'],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', $newName);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => $newName], 'tenant');
    }

    public function test_can_destroy_customer_without_orders(): void
    {
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado']);
        $salesperson = Salesperson::firstOrCreate(['name' => 'Comercial Test']);
        $country = Country::firstOrCreate(['name' => 'España']);
        $transport = Transport::firstOrCreate(
            ['name' => 'Transporte Test Customer'],
            ['vat_number' => 'B' . uniqid(), 'address' => 'Calle Test', 'emails' => 't@test.com']
        );
        $customer = Customer::create([
            'name' => 'Cliente Destroy ' . uniqid(),
            'vat_number' => 'B' . uniqid(),
            'billing_address' => 'Dir fact',
            'shipping_address' => 'Dir env',
            'payment_term_id' => $paymentTerm->id,
            'salesperson_id' => $salesperson->id,
            'country_id' => $country->id,
            'transport_id' => $transport->id,
            'emails' => 'c@test.com',
            'contact_info' => 'Contact',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v2/customers/' . $customer->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id], 'tenant');
    }
}
