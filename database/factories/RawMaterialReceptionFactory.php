<?php

namespace Database\Factories;

use App\Models\RawMaterialReception;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class RawMaterialReceptionFactory extends Factory
{
    protected $model = RawMaterialReception::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::query()->value('id') ?? Supplier::factory(),
            'date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'declared_total_amount' => $this->faker->optional(0.45)->randomFloat(2, 100, 1500),
            'declared_total_net_weight' => $this->faker->optional(0.45)->randomFloat(2, 20, 500),
            'creation_mode' => $this->faker->randomElement([
                RawMaterialReception::CREATION_MODE_LINES,
                RawMaterialReception::CREATION_MODE_PALLETS,
                null,
            ]),
        ];
    }
}
