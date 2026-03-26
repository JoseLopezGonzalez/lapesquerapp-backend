<?php

namespace Database\Factories;

use App\Models\Cebo;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cebo>
 *
 * Nota: Cebo usa 'id' como FK a products (tabla de extensión).
 * El factory requiere que el producto exista previamente.
 */
class CeboFactory extends Factory
{
    protected $model = Cebo::class;

    public function definition(): array
    {
        return [
            'id'    => Product::query()->value('id') ?? Product::factory()->create()->id,
            'fixed' => $this->faker->boolean(30),
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn () => ['fixed' => true]);
    }
}
