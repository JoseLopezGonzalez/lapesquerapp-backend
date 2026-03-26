<?php

namespace Database\Factories;

use App\Models\CommercialInteraction;
use App\Models\Customer;
use App\Models\Prospect;
use App\Models\Salesperson;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommercialInteractionFactory extends Factory
{
    protected $model = CommercialInteraction::class;

    public function definition(): array
    {
        return [
            'prospect_id' => Prospect::query()->value('id') ?? Prospect::factory(),
            'customer_id' => null,
            'salesperson_id' => Salesperson::query()->value('id') ?? Salesperson::factory(),
            'type' => $this->faker->randomElement(CommercialInteraction::types()),
            'occurred_at' => $this->faker->dateTimeBetween('-20 days', 'now'),
            'summary' => $this->faker->sentence(),
            'result' => $this->faker->randomElement(CommercialInteraction::results()),
            'next_action_note' => $this->faker->optional(0.5)->sentence(),
            'next_action_at' => $this->faker->optional(0.5)->dateTimeBetween('now', '+15 days')->format('Y-m-d'),
        ];
    }

    public function forProspect(Prospect|int $prospect): static
    {
        $prospectModel = $prospect instanceof Prospect ? $prospect : Prospect::query()->findOrFail($prospect);

        return $this->state(fn () => [
            'prospect_id' => $prospectModel->id,
            'customer_id' => null,
            'salesperson_id' => $prospectModel->salesperson_id,
        ]);
    }

    public function forCustomer(Customer|int $customer): static
    {
        $customerModel = $customer instanceof Customer ? $customer : Customer::query()->findOrFail($customer);

        return $this->state(fn () => [
            'prospect_id' => null,
            'customer_id' => $customerModel->id,
            'salesperson_id' => $customerModel->salesperson_id,
        ]);
    }

    public function pendingFollowUp(): static
    {
        return $this->state(fn () => [
            'result' => CommercialInteraction::RESULT_PENDING,
            'next_action_at' => now()->addDays(2)->format('Y-m-d'),
            'next_action_note' => 'Seguimiento comercial pendiente',
        ]);
    }

    public function closedNoFollowUp(): static
    {
        return $this->state(fn () => [
            'result' => $this->faker->randomElement([
                CommercialInteraction::RESULT_INTERESTED,
                CommercialInteraction::RESULT_NO_RESPONSE,
                CommercialInteraction::RESULT_NOT_INTERESTED,
            ]),
            'next_action_at' => null,
            'next_action_note' => null,
        ]);
    }
}
