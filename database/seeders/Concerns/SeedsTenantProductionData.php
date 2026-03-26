<?php

namespace Database\Seeders\Concerns;

use App\Models\Box;
use App\Models\CaptureZone;
use App\Models\CostCatalog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Process;
use App\Models\Product;
use App\Models\Species;
use App\Models\User;

trait SeedsTenantProductionData
{
    protected function productionAdminUser(): User
    {
        return User::query()->whereNotNull('id')->firstOrFail();
    }

    protected function productionSpecies(): Species
    {
        return Species::query()->firstOrFail();
    }

    protected function productionCaptureZone(): CaptureZone
    {
        return CaptureZone::query()->firstOrFail();
    }

    protected function productionProductPool(int $limit = 4)
    {
        $products = Product::query()->orderBy('id')->limit($limit)->get();

        if ($products->count() < $limit) {
            $products = Product::query()->orderBy('id')->get();
        }

        return $products->values();
    }

    protected function productionAvailableBoxes(int $limit = 6)
    {
        return Box::query()
            ->whereDoesntHave('productionInputs')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    protected function productionProcesses(): array
    {
        return [
            'starting' => Process::query()->where('name', 'Recepción materia prima')->first()
                ?? Process::query()->where('type', 'starting')->firstOrFail(),
            'process' => Process::query()->where('name', 'Fileteado')->first()
                ?? Process::query()->where('type', 'process')->firstOrFail(),
            'final' => Process::query()->where('name', 'Envasado al vacío')->first()
                ?? Process::query()->where('type', 'final')->firstOrFail(),
        ];
    }

    protected function productionCostCatalog(string $name, string $type, string $defaultUnit = CostCatalog::DEFAULT_UNIT_TOTAL): CostCatalog
    {
        return CostCatalog::query()->updateOrCreate(
            ['name' => $name],
            [
                'cost_type' => $type,
                'description' => 'Seeder producción dev',
                'default_unit' => $defaultUnit,
                'is_active' => true,
            ]
        );
    }

    protected function productionOrder(string $customerName): Order
    {
        $customer = Customer::query()->where('name', $customerName)->first()
            ?? Customer::query()->firstOrFail();

        return Order::query()->updateOrCreate(
            ['buyer_reference' => 'PROD-INC-'.$customer->id],
            [
                'customer_id' => $customer->id,
                'payment_term_id' => $customer->payment_term_id,
                'billing_address' => $customer->billing_address,
                'shipping_address' => $customer->shipping_address,
                'salesperson_id' => $customer->salesperson_id,
                'field_operator_id' => $customer->field_operator_id,
                'created_by_user_id' => $this->productionAdminUser()->id,
                'emails' => $customer->emails,
                'transport_id' => $customer->transport_id,
                'entry_date' => now()->subDays(2)->format('Y-m-d'),
                'load_date' => now()->addDays(2)->format('Y-m-d'),
                'status' => Order::STATUS_PENDING,
                'order_type' => Order::ORDER_TYPE_STANDARD,
            ]
        );
    }
}
