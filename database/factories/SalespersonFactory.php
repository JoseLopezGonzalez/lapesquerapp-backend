<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Salesperson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalespersonFactory extends Factory
{
    protected $model = Salesperson::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->name(),
            'emails' => $this->faker->companyEmail() . ';',
            'user_id' => null,
        ];
    }

    public function linkedUser(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory()->state([
                'role' => Role::Comercial->value,
                'active' => true,
            ]),
        ]);
    }
}
