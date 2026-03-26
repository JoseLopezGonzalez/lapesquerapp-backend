<?php

namespace Database\Factories;

use App\Models\CaptureZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CaptureZone>
 */
class CaptureZoneFactory extends Factory
{
    protected $model = CaptureZone::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Zona ' . $this->faker->unique()->bothify('FAO-##'),
        ];
    }
}
