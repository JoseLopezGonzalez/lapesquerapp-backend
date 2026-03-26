<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Prospect;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStop;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteTemplateStopFactory extends Factory
{
    protected $model = RouteTemplateStop::class;

    public function definition(): array
    {
        return [
            'route_template_id' => RouteTemplate::query()->value('id') ?? RouteTemplate::factory(),
            'position' => $this->faker->numberBetween(1, 10),
            'stop_type' => $this->faker->randomElement(['obligatoria', 'sugerida', 'oportunidad']),
            'target_type' => 'customer',
            'customer_id' => Customer::query()->value('id') ?? Customer::factory(),
            'prospect_id' => null,
            'label' => $this->faker->company(),
            'address' => $this->faker->address(),
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
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
