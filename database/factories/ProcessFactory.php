<?php

namespace Database\Factories;

use App\Models\Process;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcessFactory extends Factory
{
    protected $model = Process::class;

    public function definition(): array
    {
        return [
            'name' => 'Proceso ' . $this->faker->unique()->word(),
            'type' => $this->faker->randomElement(['process', 'final']),
        ];
    }
}
