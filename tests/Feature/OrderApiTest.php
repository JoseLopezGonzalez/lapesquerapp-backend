<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PaymentTerm;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
    }

    public function test_can_create_order_via_api_with_tenant_and_auth(): void
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        Tenant::create([
            'name' => 'Test Tenant',
            'subdomain' => 'test',
            'database' => $database,
            'active' => true,
        ]);

        $country = Country::firstOrCreate(['name' => 'España']);
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado']);
        $transport = Transport::firstOrCreate(
            ['name' => 'Transport OrderApiTest'],
            ['vat_number' => 'B' . uniqid(), 'address' => 'A', 'emails' => 't@t.com']
        );
        $salesperson = \App\Models\Salesperson::firstOrCreate(['name' => 'S']);
        $customer = Customer::create([
            'name' => 'C', 'vat_number' => 'B2', 'payment_term_id' => $paymentTerm->id,
            'billing_address' => 'B', 'shipping_address' => 'S', 'salesperson_id' => $salesperson->id,
            'emails' => 'c@c.com', 'contact_info' => 'C', 'country_id' => $country->id,
            'transport_id' => $transport->id,
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'role' => Role::Administrador->value,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('X-Tenant', 'test')
            ->withToken($token)
            ->postJson('/api/v2/orders', [
                'customer' => $customer->id,
                'entryDate' => now()->format('Y-m-d'),
                'loadDate' => now()->addDay()->format('Y-m-d'),
                'payment' => $paymentTerm->id,
                'salesperson' => $salesperson->id,
                'transport' => $transport->id,
                'billingAddress' => 'B',
                'shippingAddress' => 'S',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'data' => ['id', 'status']]);
        $this->assertDatabaseHas('orders', ['status' => 'pending'], 'tenant');
    }
}
