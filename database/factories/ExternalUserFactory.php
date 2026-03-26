<?php

namespace Database\Factories;

use App\Models\ExternalUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExternalUserFactory extends Factory
{
    protected $model = ExternalUser::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'company_name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'type' => ExternalUser::TYPE_MAQUILADOR,
            'is_active' => true,
            'notes' => $this->faker->optional(0.35)->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
