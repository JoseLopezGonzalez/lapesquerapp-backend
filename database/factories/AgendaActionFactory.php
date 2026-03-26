<?php

namespace Database\Factories;

use App\Models\AgendaAction;
use App\Models\Customer;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgendaActionFactory extends Factory
{
    protected $model = AgendaAction::class;

    public function definition(): array
    {
        return [
            'target_type' => 'prospect',
            'target_id' => Prospect::query()->value('id') ?? Prospect::factory()->create()->id,
            'scheduled_at' => $this->faker->dateTimeBetween('-2 days', '+15 days')->format('Y-m-d'),
            'description' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['pending', 'done', 'cancelled', 'reprogrammed']),
            'source_interaction_id' => null,
            'completed_interaction_id' => null,
            'previous_action_id' => null,
        ];
    }

    public function forProspect(Prospect|int $prospect): static
    {
        $prospectId = $prospect instanceof Prospect ? $prospect->id : $prospect;

        return $this->state(fn () => [
            'target_type' => 'prospect',
            'target_id' => $prospectId,
        ]);
    }

    public function forCustomer(Customer|int $customer): static
    {
        $customerId = $customer instanceof Customer ? $customer->id : $customer;

        return $this->state(fn () => [
            'target_type' => 'customer',
            'target_id' => $customerId,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
        ]);
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => 'done',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
        ]);
    }

    public function reprogrammed(): static
    {
        return $this->state(fn () => [
            'status' => 'reprogrammed',
        ]);
    }
}
