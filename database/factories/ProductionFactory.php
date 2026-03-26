<?php

namespace Database\Factories;

use App\Models\CaptureZone;
use App\Models\Production;
use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionFactory extends Factory
{
    protected $model = Production::class;

    public function definition(): array
    {
        return [
            'lot' => 'LOT-' . $this->faker->unique()->bothify('#####'),
            'date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'species_id' => Species::query()->value('id') ?? Species::factory(),
            'capture_zone_id' => CaptureZone::query()->value('id') ?? CaptureZone::factory(),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'diagram_data' => [],
            'opened_at' => now()->subHours(3),
            'closed_at' => null,
        ];
    }

    public function opened(): static
    {
        return $this->state(fn () => [
            'opened_at' => now()->subHours(3),
            'closed_at' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'opened_at' => now()->subHours(6),
            'closed_at' => now()->subHour(),
        ]);
    }
}
