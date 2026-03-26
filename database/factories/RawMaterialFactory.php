<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RawMaterial>
 *
 * Nota: RawMaterial usa 'id' como FK a products (tabla de extensión).
 * El factory requiere que el producto exista previamente.
 */
class RawMaterialFactory extends Factory
{
    protected $model = RawMaterial::class;

    public function definition(): array
    {
        return [
            'id'    => Product::query()->value('id') ?? Product::factory()->create()->id,
            'fixed' => $this->faker->boolean(20),
            'alias' => $this->faker->optional(0.5)->words(2, true),
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn () => ['fixed' => true]);
    }

    public function withAlias(): static
    {
        return $this->state(fn () => ['alias' => $this->faker->words(2, true)]);
    }
}
