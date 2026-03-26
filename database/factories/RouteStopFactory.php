<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\Prospect;
use App\Models\RouteStop;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteStopFactory extends Factory
{
    protected $model = RouteStop::class;

    public function definition(): array
    {
        return [
            'route_id' => DeliveryRoute::query()->value('id') ?? DeliveryRoute::factory(),
            'route_template_stop_id' => null,
            'position' => $this->faker->numberBetween(1, 10),
            'stop_type' => $this->faker->randomElement(RouteStop::validStopTypes()),
            'target_type' => 'customer',
            'customer_id' => Customer::query()->value('id') ?? Customer::factory(),
            'prospect_id' => null,
            'label' => $this->faker->company(),
            'address' => $this->faker->address(),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'status' => $this->faker->randomElement(RouteStop::validStatuses()),
            'result_type' => null,
            'result_notes' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => RouteStop::STATUS_COMPLETED,
            'result_type' => $this->faker->randomElement(RouteStop::validResultTypes()),
            'completed_at' => now()->subHour(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => RouteStop::STATUS_PENDING,
            'result_type' => null,
            'result_notes' => null,
            'completed_at' => null,
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn () => [
            'status' => RouteStop::STATUS_SKIPPED,
            'result_type' => RouteStop::RESULT_TYPE_NO_CONTACT,
            'completed_at' => now()->subMinutes(20),
        ]);
    }

    public function forCustomer(Customer|int $customer): static
    {
        $customerId = $customer instanceof Customer ? $customer->id : $customer;

        return $this->state(fn () => [
            'target_type' => 'customer',
            'customer_id' => $customerId,
            'prospect_id' => null,
        ]);
    }

    public function forProspect(): static
    {
        return $this->state(fn () => [
            'target_type' => 'prospect',
            'customer_id' => null,
            'prospect_id' => Prospect::query()->value('id') ?? Prospect::factory(),
            'label' => $this->faker->company(),
        ]);
    }

    public function forProspectModel(Prospect|int $prospect): static
    {
        $prospectId = $prospect instanceof Prospect ? $prospect->id : $prospect;

        return $this->state(fn () => [
            'target_type' => 'prospect',
            'customer_id' => null,
            'prospect_id' => $prospectId,
        ]);
    }

    public function forLocation(): static
    {
        return $this->state(fn () => [
            'target_type' => 'location',
            'customer_id' => null,
            'prospect_id' => null,
            'label' => 'Parada logística ' . $this->faker->city(),
            'address' => $this->faker->address(),
        ]);
    }
}
