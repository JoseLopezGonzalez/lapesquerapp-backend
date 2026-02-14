<?php

namespace Tests\Unit\Services;

use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentTerm;
use App\Models\Transport;
use App\Services\v2\OrderStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderStoreServiceTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
    }

    public function test_store_creates_order_with_minimal_data(): void
    {
        $country = Country::firstOrCreate(['name' => 'España']);
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado']);
        $transport = Transport::firstOrCreate(
            ['vat_number' => 'B12345678'],
            ['name' => 'Transporte Test', 'address' => 'Calle Test', 'emails' => 't@test.com']
        );
        $salesperson = \App\Models\Salesperson::firstOrCreate(['name' => 'Comercial Test']);
        $customer = Customer::create([
            'name' => 'Cliente Test',
            'vat_number' => 'B87654321-' . uniqid(),
            'payment_term_id' => $paymentTerm->id,
            'billing_address' => 'Dir fact',
            'shipping_address' => 'Dir env',
            'salesperson_id' => $salesperson->id,
            'emails' => 'c@test.com',
            'contact_info' => 'Contact',
            'country_id' => $country->id,
            'transport_id' => $transport->id,
        ]);

        $validated = [
            'customer' => $customer->id,
            'entryDate' => now()->format('Y-m-d'),
            'loadDate' => now()->addDay()->format('Y-m-d'),
            'payment' => $paymentTerm->id,
            'salesperson' => $salesperson->id,
            'transport' => $transport->id,
            'billingAddress' => 'Billing',
            'shippingAddress' => 'Shipping',
        ];

        $order = OrderStoreService::store($validated);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);
        $this->assertEquals('pending', $order->status);
    }
}
