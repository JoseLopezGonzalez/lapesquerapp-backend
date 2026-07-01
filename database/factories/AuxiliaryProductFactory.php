<?php

namespace Database\Factories;

use App\Models\AuxiliaryProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuxiliaryProductFactory extends Factory
{
    protected $model = AuxiliaryProduct::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'reference' => strtoupper($this->faker->bothify('AUX-###')),
            'unit' => $this->faker->randomElement(['ud', 'kg', 'saco', 'palet', 'servicio']),
            'default_price' => $this->faker->randomFloat(4, 0.05, 20),
            'notes' => $this->faker->optional()->sentence(),
            'active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['active' => false]);
    }
}
