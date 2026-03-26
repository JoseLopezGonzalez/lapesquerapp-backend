<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::query()->value('id') ?? Order::factory(),
            'description' => $this->faker->sentence(),
            'status' => Incident::STATUS_OPEN,
            'resolution_type' => null,
            'resolution_notes' => null,
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => Incident::STATUS_RESOLVED,
            'resolution_type' => $this->faker->randomElement(Incident::getValidResolutionTypes()),
            'resolution_notes' => $this->faker->sentence(),
            'resolved_at' => now()->subHour(),
        ]);
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => Incident::STATUS_OPEN,
            'resolution_type' => null,
            'resolution_notes' => null,
            'resolved_at' => null,
        ]);
    }
}
