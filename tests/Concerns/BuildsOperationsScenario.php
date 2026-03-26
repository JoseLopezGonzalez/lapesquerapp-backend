<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentTerm;
use App\Models\Salesperson;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\User;

/**
 * Trait reutilizable para tests de pedidos y clientes.
 * Proporciona el escenario de dependencias mínimas para los flujos de ventas.
 */
trait BuildsOperationsScenario
{
    /**
     * Crea el tenant de prueba + usuario admin y guarda subdomain y token.
     * Requiere que $this->tenantSubdomain y $this->token sean propiedades del test.
     */
    protected function createTenantAndAdminUser(string $prefix = 'ops'): array
    {
        $database = config('database.connections.' . config('database.default') . '.database')
            ?? env('DB_DATABASE', 'testing');
        $slug = $prefix . '-' . uniqid();

        Tenant::create([
            'name'      => 'Test Tenant ' . strtoupper($prefix),
            'subdomain' => $slug,
            'database'  => $database,
            'status'    => 'active',
        ]);

        $user = User::create([
            'name'   => 'Test Admin',
            'email'  => $slug . '@test.com',
            'role'   => Role::Administrador->value,
            'active' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        return ['slug' => $slug, 'user' => $user, 'token' => $token];
    }

    /**
     * Crea o recupera las dependencias mínimas para un Customer u Order:
     * Country, PaymentTerm, Transport, Salesperson.
     */
    protected function createSalesContext(string $suffix = ''): array
    {
        $label = $suffix ?: 'Ops';

        $country = Country::query()->firstOrCreate(
            ['name' => 'España ' . $label],
            ['name' => 'España ' . $label]
        );

        $paymentTerm = PaymentTerm::query()->firstOrCreate(
            ['name' => 'Contado ' . $label],
            ['name' => 'Contado ' . $label]
        );

        $transport = Transport::query()->firstOrCreate(
            ['name' => 'Transporte ' . $label],
            [
                'vat_number' => 'B' . substr(uniqid(), -7),
                'address'    => 'Calle Puerto 1',
                'emails'     => 'transporte.' . strtolower($label) . '@pesquerapp.test',
            ]
        );

        $salesperson = Salesperson::query()->firstOrCreate(
            ['name' => 'Comercial ' . $label],
            ['name' => 'Comercial ' . $label]
        );

        return compact('country', 'paymentTerm', 'transport', 'salesperson');
    }

    /**
     * Crea un Customer con las dependencias del contexto de ventas dado.
     */
    protected function createCustomerForTest(array $context, string $suffix = ''): Customer
    {
        return Customer::factory()->create([
            'payment_term_id' => $context['paymentTerm']->id,
            'salesperson_id'  => $context['salesperson']->id,
            'country_id'      => $context['country']->id,
            'transport_id'    => $context['transport']->id,
        ]);
    }

    /**
     * Crea un Order con las dependencias del contexto de ventas dado.
     */
    protected function createOrderForTest(Customer $customer, array $context): Order
    {
        return Order::factory()->create([
            'customer_id'     => $customer->id,
            'payment_term_id' => $context['paymentTerm']->id,
            'salesperson_id'  => $context['salesperson']->id,
            'transport_id'    => $context['transport']->id,
        ]);
    }
}
