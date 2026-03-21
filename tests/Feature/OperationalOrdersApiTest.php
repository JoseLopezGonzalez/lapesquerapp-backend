<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CaptureZone;
use App\Models\Country;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\FieldOperator;
use App\Models\FishingGear;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\RouteStop;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\User;
use App\Models\Species;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class OperationalOrdersApiTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    private string $tenantSubdomain;
    private User $adminUser;
    private User $fieldUser;
    private FieldOperator $fieldOperator;
    private Customer $customer;
    private Product $product;
    private Tax $tax;
    private string $customerName;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();

        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');
        $slug = 'operational-orders-' . uniqid();
        Tenant::create([
            'name' => 'Operational Orders Tenant',
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

        $paymentTerm = PaymentTerm::firstOrCreate(['name' => 'Contado OO']);
        $country = Country::firstOrCreate(['name' => 'España OO']);
        $transport = Transport::firstOrCreate(
            ['name' => 'Transport OO'],
            ['vat_number' => 'B' . uniqid(), 'address' => 'Street', 'emails' => 'transport@example.com']
        );

        $this->customerName = 'Cliente OO ' . Str::lower(Str::random(8));

        $this->customer = Customer::create([
            'name' => $this->customerName,
            'vat_number' => null,
            'payment_term_id' => $paymentTerm->id,
            'billing_address' => 'B',
            'shipping_address' => 'S',
            'salesperson_id' => null,
            'field_operator_id' => $this->fieldOperator->id,
            'operational_status' => 'alta_operativa',
            'created_by_user_id' => $this->adminUser->id,
            'emails' => null,
            'contact_info' => null,
            'country_id' => $country->id,
            'transport_id' => $transport->id,
        ]);

        $this->tax = Tax::firstOrCreate(
            ['name' => 'IVA OO ' . Str::lower(Str::random(6))],
            ['rate' => 21]
        );
        $fishingGear = FishingGear::firstOrCreate([
            'name' => 'Arte OO ' . Str::lower(Str::random(6)),
        ]);
        $species = Species::create([
            'name' => 'Especie OO ' . Str::lower(Str::random(6)),
            'scientific_name' => 'Species operational orders',
            'fao' => 'OO1',
            'image' => null,
            'fishing_gear_id' => $fishingGear->id,
        ]);
        $captureZone = CaptureZone::create([
            'name' => 'Zona OO ' . Str::lower(Str::random(6)),
        ]);
        $this->product = Product::create([
            'name' => 'Producto OO ' . Str::lower(Str::random(6)),
            'species_id' => $species->id,
            'capture_zone_id' => $captureZone->id,
            'article_gtin' => '8400000000001',
            'box_gtin' => '9400000000001',
            'pallet_gtin' => '9900000000001',
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

    private function createAssignedOrder(): Order
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'entry_date' => now()->format('Y-m-d'),
            'load_date' => now()->addDay()->format('Y-m-d'),
            'payment_term_id' => null,
            'salesperson_id' => null,
            'field_operator_id' => $this->fieldOperator->id,
            'created_by_user_id' => $this->adminUser->id,
            'transport_id' => null,
            'billing_address' => null,
            'shipping_address' => null,
            'emails' => null,
            'status' => Order::STATUS_PENDING,
            'order_type' => Order::ORDER_TYPE_STANDARD,
        ]);

        OrderPlannedProductDetail::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'tax_id' => $this->tax->id,
            'quantity' => 10,
            'boxes' => 2,
            'unit_price' => 5,
        ]);

        return $order;
    }

    public function test_field_user_can_list_and_update_only_assigned_orders(): void
    {
        $order = $this->createAssignedOrder();
        $otherOrder = Order::create([
            'customer_id' => $this->customer->id,
            'entry_date' => now()->format('Y-m-d'),
            'load_date' => now()->addDay()->format('Y-m-d'),
            'payment_term_id' => null,
            'salesperson_id' => null,
            'field_operator_id' => null,
            'created_by_user_id' => $this->adminUser->id,
            'transport_id' => null,
            'billing_address' => null,
            'shipping_address' => null,
            'emails' => null,
            'status' => Order::STATUS_PENDING,
            'order_type' => Order::ORDER_TYPE_STANDARD,
        ]);

        $index = $this->withHeaders($this->headersFor($this->fieldUser))
            ->getJson('/api/v2/field/orders');

        $index->assertStatus(200);
        $index->assertJsonFragment(['id' => $order->id]);
        $returnedIds = collect($index->json('data'))->pluck('id')->all();
        $this->assertContains($order->id, $returnedIds);
        $this->assertNotContains($otherOrder->id, $returnedIds);

        $update = $this->withHeaders($this->headersFor($this->fieldUser))
            ->putJson('/api/v2/field/orders/' . $order->id, [
                'status' => 'finished',
                'plannedProducts' => [[
                    'product' => $this->product->id,
                    'tax' => $this->tax->id,
                    'quantity' => 12,
                    'boxes' => 3,
                    'unitPrice' => 5.25,
                ]],
            ]);

        $update->assertStatus(200);
        $update->assertJsonPath('data.status', 'finished');
        $this->assertDatabaseHas('order_planned_product_details', [
            'order_id' => $order->id,
            'quantity' => 12,
            'boxes' => 3,
        ], 'tenant');
    }

    public function test_field_user_can_update_only_status_without_resending_planned_products(): void
    {
        $order = $this->createAssignedOrder();

        $response = $this->withHeaders($this->headersFor($this->fieldUser))
            ->putJson('/api/v2/field/orders/' . $order->id, [
                'status' => 'finished',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'finished');
        $this->assertDatabaseHas('order_planned_product_details', [
            'order_id' => $order->id,
            'quantity' => 10,
            'boxes' => 2,
        ], 'tenant');
    }

    public function test_field_user_can_create_autoventa_with_new_operational_customer(): void
    {
        $response = $this->withHeaders($this->headersFor($this->fieldUser))
            ->postJson('/api/v2/field/autoventas', [
                'newCustomerName' => 'Cliente Ruta Nuevo',
                'entryDate' => now()->format('Y-m-d'),
                'loadDate' => now()->format('Y-m-d'),
                'invoiceRequired' => true,
                'observations' => 'Observaciones test',
                'items' => [[
                    'productId' => $this->product->id,
                    'boxesCount' => 1,
                    'totalWeight' => 5.5,
                    'unitPrice' => 12.5,
                    'tax' => $this->tax->id,
                ]],
                'boxes' => [[
                    'productId' => $this->product->id,
                    'lot' => 'LOTE-TEST',
                    'netWeight' => 5.5,
                    'grossWeight' => 5.8,
                    'gs1128' => 'GS1-TEST',
                ]],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.orderType', 'autoventa');
        $this->assertDatabaseHas('customers', [
            'name' => 'Cliente Ruta Nuevo',
            'field_operator_id' => $this->fieldOperator->id,
            'operational_status' => 'alta_operativa',
            'salesperson_id' => null,
        ], 'tenant');
        $this->assertDatabaseHas('orders', [
            'order_type' => 'autoventa',
            'field_operator_id' => $this->fieldOperator->id,
            'created_by_user_id' => $this->fieldUser->id,
        ], 'tenant');
    }

    public function test_field_user_can_create_autoventa_for_existing_assigned_customer(): void
    {
        $response = $this->withHeaders($this->headersFor($this->fieldUser))
            ->postJson('/api/v2/field/autoventas', [
                'customer' => $this->customer->id,
                'entryDate' => now()->format('Y-m-d'),
                'loadDate' => now()->format('Y-m-d'),
                'invoiceRequired' => false,
                'items' => [[
                    'productId' => $this->product->id,
                    'boxesCount' => 1,
                    'totalWeight' => 3.5,
                    'unitPrice' => 10.5,
                    'tax' => $this->tax->id,
                ]],
                'boxes' => [[
                    'productId' => $this->product->id,
                    'lot' => 'LOTE-ASSIGNED',
                    'netWeight' => 3.5,
                    'grossWeight' => 3.8,
                    'gs1128' => 'GS1-ASSIGNED',
                ]],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.customer.id', $this->customer->id);
    }

    public function test_field_user_cannot_create_autoventa_for_existing_unassigned_customer(): void
    {
        $paymentTerm = PaymentTerm::first();
        $country = Country::first();
        $transport = Transport::first();

        $foreignCustomer = Customer::create([
            'name' => 'Cliente Sin Acceso',
            'vat_number' => null,
            'payment_term_id' => $paymentTerm?->id,
            'billing_address' => 'B',
            'shipping_address' => 'S',
            'salesperson_id' => null,
            'field_operator_id' => null,
            'operational_status' => 'normal',
            'created_by_user_id' => $this->adminUser->id,
            'emails' => null,
            'contact_info' => null,
            'country_id' => $country?->id,
            'transport_id' => $transport?->id,
        ]);

        $response = $this->withHeaders($this->headersFor($this->fieldUser))
            ->postJson('/api/v2/field/autoventas', [
                'customer' => $foreignCustomer->id,
                'entryDate' => now()->format('Y-m-d'),
                'loadDate' => now()->format('Y-m-d'),
                'invoiceRequired' => false,
                'items' => [[
                    'productId' => $this->product->id,
                    'boxesCount' => 1,
                    'totalWeight' => 3.5,
                    'unitPrice' => 10.5,
                    'tax' => $this->tax->id,
                ]],
                'boxes' => [[
                    'productId' => $this->product->id,
                    'lot' => 'LOTE-NO-ACCESS',
                    'netWeight' => 3.5,
                    'grossWeight' => 3.8,
                    'gs1128' => 'GS1-NO-ACCESS',
                ]],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer']);
    }

    public function test_field_user_without_field_operator_gets_forbidden_on_field_orders_and_routes(): void
    {
        $orphanFieldUser = User::create([
            'name' => 'Field Orphan',
            'email' => 'field-orphan-' . uniqid() . '@test.com',
            'role' => Role::RepartidorAutoventa->value,
            'active' => true,
        ]);

        $headers = $this->headersFor($orphanFieldUser);

        $this->withHeaders($headers)
            ->getJson('/api/v2/field/orders')
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->getJson('/api/v2/field/routes')
            ->assertStatus(403);
    }

    public function test_field_user_cannot_link_autoventa_to_foreign_or_inconsistent_route_context(): void
    {
        $otherUser = User::create([
            'name' => 'Other Field User',
            'email' => 'other-field-' . uniqid() . '@test.com',
            'role' => Role::RepartidorAutoventa->value,
            'active' => true,
        ]);
        $otherFieldOperator = FieldOperator::create([
            'name' => 'Otro Repartidor',
            'user_id' => $otherUser->id,
        ]);

        $foreignRoute = DeliveryRoute::create([
            'name' => 'Ruta Ajena',
            'route_date' => now()->format('Y-m-d'),
            'status' => 'planned',
            'field_operator_id' => $otherFieldOperator->id,
            'created_by_user_id' => $this->adminUser->id,
        ]);
        $foreignRouteStop = RouteStop::create([
            'route_id' => $foreignRoute->id,
            'position' => 1,
            'stop_type' => 'obligatoria',
            'target_type' => 'customer',
            'customer_id' => $this->customer->id,
            'label' => 'Parada ajena',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->headersFor($this->fieldUser))
            ->postJson('/api/v2/field/autoventas', [
                'customer' => $this->customer->id,
                'entryDate' => now()->format('Y-m-d'),
                'loadDate' => now()->format('Y-m-d'),
                'invoiceRequired' => true,
                'items' => [[
                    'productId' => $this->product->id,
                    'boxesCount' => 1,
                    'totalWeight' => 5.5,
                    'unitPrice' => 12.5,
                    'tax' => $this->tax->id,
                ]],
                'boxes' => [[
                    'productId' => $this->product->id,
                    'lot' => 'LOTE-FOREIGN',
                    'netWeight' => 5.5,
                    'grossWeight' => 5.8,
                    'gs1128' => 'GS1-FOREIGN',
                ]],
                'routeId' => $foreignRoute->id,
                'routeStopId' => $foreignRouteStop->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['routeId', 'routeStopId']);
    }
}
