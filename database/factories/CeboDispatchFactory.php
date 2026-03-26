<?php

namespace Database\Factories;

use App\Models\CeboDispatch;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class CeboDispatchFactory extends Factory
{
    protected $model = CeboDispatch::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::query()->value('id') ?? Supplier::factory(),
            'date' => $this->faker->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'export_type' => $this->faker->randomElement(['facilcom', 'a3erp']),
        ];
    }
}
