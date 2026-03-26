<?php

namespace Database\Factories;

use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Species>
 */
class SpeciesFactory extends Factory
{
    protected $model = Species::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Especie ' . $this->faker->unique()->word,
            'scientific_name' => $this->faker->unique()->lexify('Species ?????'),
            'fao' => strtoupper($this->faker->unique()->lexify('???')),
            'image' => $this->faker->imageUrl(),
        ];
    }
}
