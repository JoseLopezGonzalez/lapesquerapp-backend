<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'species_id' => $this->faker->numberBetween(1, 5),
            'capture_zone_id' => $this->faker->numberBetween(1, 3),
            'article_gtin' => $this->faker->numerify('84' . str_repeat('#', 11)),
            'box_gtin' => $this->faker->numerify('984' . str_repeat('#', 11)),
            'pallet_gtin' => $this->faker->numerify('984' . str_repeat('#', 11)),
        ];
    }
}
