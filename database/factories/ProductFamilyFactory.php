<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\ProductFamily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductFamily>
 */
class ProductFamilyFactory extends Factory
{
    protected $model = ProductFamily::class;

    public function definition(): array
    {
        $names = ['Bacalao', 'Merluza', 'Salmón', 'Boquerón', 'Sardina', 'Atún', 'Pulpo', 'Sepia', 'Mejillón', 'Gamba'];

        return [
            'name'        => $this->faker->unique()->randomElement($names) . ' ' . $this->faker->numberBetween(1, 99),
            'description' => $this->faker->optional(0.5)->sentence(),
            'category_id' => ProductCategory::query()->value('id') ?? ProductCategory::factory(),
            'active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
