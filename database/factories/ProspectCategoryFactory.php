<?php

namespace Database\Factories;

use App\Models\ProspectCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProspectCategoryFactory extends Factory
{
    protected $model = ProspectCategory::class;

    public function definition(): array
    {
        $names = ['Restaurante', 'Mayorista', 'Distribuidor', 'Gran empresa', 'Retail', 'Hotel'];

        return [
            'name' => $this->faker->unique()->randomElement($names).' '.$this->faker->numberBetween(1, 99),
            'description' => $this->faker->optional(0.6)->sentence(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
