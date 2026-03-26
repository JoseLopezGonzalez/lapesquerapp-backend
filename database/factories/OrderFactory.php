<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\FieldOperator;
use App\Models\Incoterm;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\PaymentTerm;
use App\Models\Salesperson;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $entryDate = $this->faker->dateTimeBetween('-30 days', '-2 days');
        $loadDate = (clone $entryDate)->modify('+' . $this->faker->numberBetween(1, 5) . ' days');

        return [
            'customer_id' => Customer::query()->value('id') ?? Customer::factory(),
            'payment_term_id' => PaymentTerm::query()->value('id') ?? PaymentTerm::factory(),
            'billing_address' => $this->faker->address(),
            'shipping_address' => $this->faker->address(),
            'transportation_notes' => $this->faker->optional(0.25)->sentence(),
            'production_notes' => $this->faker->optional(0.2)->sentence(),
            'accounting_notes' => $this->faker->optional(0.15)->sentence(),
            'salesperson_id' => Salesperson::query()->value('id') ?? Salesperson::factory(),
            'field_operator_id' => $this->faker->optional(0.35)->passthrough(FieldOperator::query()->value('id') ?? FieldOperator::factory()),
            'created_by_user_id' => User::query()->value('id') ?? User::factory(),
            'emails' => $this->faker->companyEmail() . ';',
            'transport_id' => Transport::query()->value('id') ?? Transport::factory(),
            'entry_date' => $entryDate->format('Y-m-d'),
            'load_date' => $loadDate->format('Y-m-d'),
            'status' => Order::STATUS_PENDING,
            'order_type' => $this->faker->randomElement(Order::getValidOrderTypes()),
            'buyer_reference' => 'REF-' . $this->faker->unique()->bothify('#####'),
            'incoterm_id' => $this->faker->optional(0.6)->passthrough(Incoterm::query()->value('id') ?? Incoterm::factory()),
            'route_id' => null,
            'route_stop_id' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_PENDING]);
    }

    public function finished(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_FINISHED]);
    }

    public function incident(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_INCIDENT]);
    }

    public function withLines(int $count = 3): static
    {
        return $this->afterCreating(function (Order $order) use ($count): void {
            OrderPlannedProductDetail::factory()
                ->count($count)
                ->for($order, 'order')
                ->create();
        });
    }
}
