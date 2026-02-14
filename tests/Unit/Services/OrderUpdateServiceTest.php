<?php

namespace Tests\Unit\Services;

use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentTerm;
use App\Models\Transport;
use App\Services\v2\OrderUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrderUpdateServiceTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
    }

    public function test_update_throws_validation_exception_when_load_date_before_entry_date(): void
    {
        $this->createMinimalOrderDependencies($country, $paymentTerm, $transport, $salesperson, $customer);
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

        $this->expectException(ValidationException::class);
        OrderUpdateService::update($order, [
            'entryDate' => now()->addDays(2)->format('Y-m-d'),
            'loadDate' => now()->format('Y-m-d'),
        ]);
    }

    public function test_update_changes_buyer_reference(): void
    {
        $this->createMinimalOrderDependencies($country, $paymentTerm, $transport, $salesperson, $customer);
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

        $updated = OrderUpdateService::update($order, ['buyerReference' => 'REF-123']);

        $this->assertEquals('REF-123', $updated->buyer_reference);
    }

    private function createMinimalOrderDependencies(&$country, &$paymentTerm, &$transport, &$salesperson, &$customer): void
    {
        $country = Country::firstOrCreate(['name' => 'España']);
        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado']);
        $transport = Transport::firstOrCreate(
            ['vat_number' => 'B1'],
            ['name' => 'T', 'address' => 'A', 'emails' => 't@t.com']
        );
        $salesperson = \App\Models\Salesperson::firstOrCreate(['name' => 'S']);
        $customer = Customer::create([
            'name' => 'C-' . uniqid(), 'vat_number' => 'B2-' . uniqid(), 'payment_term_id' => $paymentTerm->id,
            'billing_address' => 'B', 'shipping_address' => 'S', 'salesperson_id' => $salesperson->id,
            'emails' => 'c@c.com', 'contact_info' => 'C', 'country_id' => $country->id,
            'transport_id' => $transport->id,
        ]);
    }
}
