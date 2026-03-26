<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Customer;
use App\Models\Prospect;
use App\Models\Salesperson;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProspectFactory extends Factory
{
    protected $model = Prospect::class;

    public function definition(): array
    {
        return [
            'salesperson_id' => Salesperson::query()->value('id') ?? Salesperson::factory(),
            'company_name' => $this->faker->company(),
            'address' => $this->faker->address(),
            'website' => $this->faker->optional(0.65)->url(),
            'country_id' => $this->faker->optional(0.75)->passthrough(Country::query()->value('id') ?? Country::factory()),
            'species_interest' => [$this->faker->randomElement(['Pulpo', 'Merluza', 'Caballa', 'Calamar'])],
            'origin' => $this->faker->randomElement(Prospect::origins()),
            'status' => Prospect::STATUS_NEW,
            'customer_id' => null,
            'next_action_at' => $this->faker->optional(0.6)->dateTimeBetween('now', '+20 days')->format('Y-m-d'),
            'next_action_note' => $this->faker->optional(0.5)->sentence(),
            'notes' => $this->faker->optional(0.4)->sentence(),
            'commercial_interest_notes' => $this->faker->optional(0.4)->sentence(),
            'last_contact_at' => $this->faker->optional(0.55)->dateTimeBetween('-30 days', 'now'),
            'last_offer_at' => $this->faker->optional(0.25)->dateTimeBetween('-20 days', 'now'),
            'lost_reason' => null,
        ];
    }

    public function new(): static
    {
        return $this->state(fn () => [
            'status' => Prospect::STATUS_NEW,
            'customer_id' => null,
            'lost_reason' => null,
        ]);
    }

    public function following(): static
    {
        return $this->state(fn () => [
            'status' => Prospect::STATUS_FOLLOWING,
            'last_contact_at' => now()->subDays(2),
            'customer_id' => null,
            'lost_reason' => null,
        ]);
    }

    public function offerSent(): static
    {
        return $this->state(fn () => [
            'status' => Prospect::STATUS_OFFER_SENT,
            'last_offer_at' => now()->subDay(),
            'lost_reason' => null,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn () => [
            'status' => Prospect::STATUS_CUSTOMER,
            'customer_id' => Customer::query()->value('id') ?? Customer::factory(),
            'lost_reason' => null,
        ]);
    }

    public function discarded(): static
    {
        return $this->state(fn () => [
            'status' => Prospect::STATUS_DISCARDED,
            'customer_id' => null,
            'lost_reason' => $this->faker->sentence(),
        ]);
    }

    public function assignedTo(Salesperson|int $salesperson): static
    {
        $salespersonId = $salesperson instanceof Salesperson ? $salesperson->id : $salesperson;

        return $this->state(fn () => [
            'salesperson_id' => $salespersonId,
        ]);
    }
}
