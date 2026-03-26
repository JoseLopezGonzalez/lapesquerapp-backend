<?php

namespace Database\Seeders\Concerns;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\FieldOperator;
use App\Models\Order;
use App\Models\Prospect;
use App\Models\Salesperson;
use App\Models\User;

trait SeedsTenantRoutesData
{
    protected function routesAdminUser(): User
    {
        return User::query()->where('role', Role::Administrador->value)->first()
            ?? User::query()->firstOrCreate(
                ['email' => 'routes.admin@pesquerapp.com'],
                [
                    'name' => 'Routes Admin',
                    'role' => Role::Administrador->value,
                    'active' => true,
                ]
            );
    }

    protected function routesFieldUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'repartidor@pesquerapp.com'],
            [
                'name' => 'Miguel Repartidor',
                'role' => Role::RepartidorAutoventa->value,
                'active' => true,
            ]
        );
    }

    protected function routesFieldOperator(): FieldOperator
    {
        $user = $this->routesFieldUser();

        return FieldOperator::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Miguel Repartidor',
                'emails' => 'repartidor@pesquerapp.com;',
            ]
        );
    }

    protected function routesPrimarySalesperson(): Salesperson
    {
        return Salesperson::query()->where('name', 'CRM Comercial Principal')->first()
            ?? Salesperson::query()->whereNotNull('user_id')->first()
            ?? Salesperson::factory()->linkedUser()->create([
                'name' => 'CRM Comercial Principal',
                'emails' => 'crm.comercial@pesquerapp.com;',
            ]);
    }

    protected function routesSecondarySalesperson(): Salesperson
    {
        return Salesperson::query()->where('name', 'CRM Comercial Secundario')->first()
            ?? Salesperson::query()->where('id', '!=', $this->routesPrimarySalesperson()->id)->first()
            ?? Salesperson::factory()->linkedUser()->create([
                'name' => 'CRM Comercial Secundario',
                'emails' => 'crm.comercial.2@pesquerapp.com;',
            ]);
    }

    protected function routesCustomer(string $name, ?int $fieldOperatorId = null, ?int $salespersonId = null): Customer
    {
        $customer = Customer::query()->where('name', $name)->first();

        if ($customer) {
            if ($fieldOperatorId && ! $customer->field_operator_id) {
                $customer->update(['field_operator_id' => $fieldOperatorId]);
            }

            if ($salespersonId && ! $customer->salesperson_id) {
                $customer->update(['salesperson_id' => $salespersonId]);
            }

            return $customer->fresh();
        }

        return Customer::factory()->create([
            'name' => $name,
            'field_operator_id' => $fieldOperatorId,
            'salesperson_id' => $salespersonId,
        ]);
    }

    protected function routesProspect(string $companyName, int $salespersonId): Prospect
    {
        return Prospect::query()->where('company_name', $companyName)->first()
            ?? Prospect::factory()->assignedTo($salespersonId)->create([
                'company_name' => $companyName,
            ]);
    }

    protected function routesOrder(string $buyerReference, Customer $customer, ?int $fieldOperatorId = null, ?int $salespersonId = null): Order
    {
        return Order::query()->updateOrCreate(
            ['buyer_reference' => $buyerReference],
            [
                'customer_id' => $customer->id,
                'payment_term_id' => $customer->payment_term_id,
                'billing_address' => $customer->billing_address,
                'shipping_address' => $customer->shipping_address,
                'salesperson_id' => $salespersonId,
                'field_operator_id' => $fieldOperatorId,
                'created_by_user_id' => $this->routesAdminUser()->id,
                'emails' => $customer->emails,
                'transport_id' => $customer->transport_id,
                'entry_date' => now()->subDay()->format('Y-m-d'),
                'load_date' => now()->addDay()->format('Y-m-d'),
                'status' => Order::STATUS_PENDING,
                'order_type' => Order::ORDER_TYPE_STANDARD,
                'route_id' => null,
                'route_stop_id' => null,
            ]
        );
    }
}
