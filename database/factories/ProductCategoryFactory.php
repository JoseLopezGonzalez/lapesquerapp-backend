<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        $names = ['Pescado Fresco', 'Congelado', 'Semiconserva', 'Conserva', 'Marisco', 'Cefalópodo', 'Bivalvo', 'Crustáceo'];

        return [
            'name'        => $this->faker->unique()->randomElement($names) . ' ' . $this->faker->numberBetween(1, 99),
            'description' => $this->faker->optional(0.6)->sentence(),
            'active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
