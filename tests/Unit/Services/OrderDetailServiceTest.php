<?php

namespace Tests\Unit\Services;

use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentTerm;
use App\Models\Transport;
use App\Services\v2\OrderDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderDetailServiceTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
    }

    public function test_get_order_for_detail_returns_order_with_relations(): void
    {
        $country = Country::create(['name' => 'España']);
        $paymentTerm = PaymentTerm::create(['name' => 'Contado']);
        $transport = Transport::create([
            'name' => 'T', 'vat_number' => 'B1', 'address' => 'A', 'emails' => 't@t.com',
        ]);
        $salesperson = \App\Models\Salesperson::create(['name' => 'S']);
        $customer = Customer::create([
            'name' => 'C', 'vat_number' => 'B2', 'payment_term_id' => $paymentTerm->id,
            'billing_address' => 'B', 'shipping_address' => 'S', 'salesperson_id' => $salesperson->id,
            'emails' => 'c@c.com', 'contact_info' => 'C', 'country_id' => $country->id,
            'transport_id' => $transport->id,
        ]);
        $order = Order::create([
            'customer_id' => $customer->id,
            'payment_term_id' => $paymentTerm->id,
            'salesperson_id' => $salesperson->id,
            'transport_id' => $transport->id,
            'entry_date' => now(),
            'load_date' => now()->addDay(),
            'status' => 'pending',
            'billing_address' => 'B',
            'shipping_address' => 'S',
            'emails' => 'e@e.com',
        ]);

        $result = OrderDetailService::getOrderForDetail((string) $order->id);

        $this->assertSame($order->id, $result->id);
        $this->assertTrue($result->relationLoaded('customer'));
    }
}
