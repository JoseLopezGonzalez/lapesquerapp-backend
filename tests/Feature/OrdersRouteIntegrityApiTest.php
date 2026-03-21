<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\FieldOperator;
use App\Models\Order;
use App\Models\PaymentTerm;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OrdersRouteIntegrityApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();

        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        Tenant::create([
            'name' => 'Orders Route Integrity Tenant',
            'subdomain' => 'orders-route-integrity-' . uniqid(),
            'database' => $database,
            'status' => 'active',
        ]);
    }

    public function test_deleting_route_and_stop_nulls_order_references(): void
    {
        $adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@test.com',
            'role' => Role::Administrador->value,
            'active' => true,
        ]);

        $fieldUser = User::create([
            'name' => 'Field',
            'email' => 'field-' . uniqid() . '@test.com',
            'role' => Role::RepartidorAutoventa->value,
            'active' => true,
        ]);

        $fieldOperator = FieldOperator::create([
            'name' => 'Field Operator',
            'user_id' => $fieldUser->id,
        ]);

        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado Integrity']);

        $customer = Customer::create([
            'name' => 'Cliente Integrity',
            'payment_term_id' => $paymentTerm->id,
            'salesperson_id' => null,
            'field_operator_id' => $fieldOperator->id,
            'operational_status' => 'alta_operativa',
            'created_by_user_id' => $adminUser->id,
        ]);

        $route = DeliveryRoute::create([
            'name' => 'Ruta Integrity',
            'route_date' => now()->format('Y-m-d'),
            'status' => DeliveryRoute::STATUS_PLANNED,
            'field_operator_id' => $fieldOperator->id,
            'created_by_user_id' => $adminUser->id,
        ]);

        $stop = RouteStop::create([
            'route_id' => $route->id,
            'position' => 1,
            'stop_type' => RouteStop::STOP_TYPE_REQUIRED,
            'target_type' => 'customer',
            'customer_id' => $customer->id,
            'status' => RouteStop::STATUS_PENDING,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'entry_date' => now()->format('Y-m-d'),
            'load_date' => now()->format('Y-m-d'),
            'salesperson_id' => null,
            'field_operator_id' => $fieldOperator->id,
            'created_by_user_id' => $adminUser->id,
            'status' => Order::STATUS_PENDING,
            'order_type' => Order::ORDER_TYPE_STANDARD,
            'route_id' => $route->id,
            'route_stop_id' => $stop->id,
        ]);

        $route->delete();
        $order->refresh();

        $this->assertNull($order->route_id);
        $this->assertNull($order->route_stop_id);
    }
}
