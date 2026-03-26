<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\FieldOperator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldOperatorFactory extends Factory
{
    protected $model = FieldOperator::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'emails' => $this->faker->safeEmail() . ';',
            'user_id' => null,
        ];
    }

    public function linkedUser(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory()->state([
                'role' => Role::RepartidorAutoventa->value,
                'active' => true,
            ]),
        ]);
    }
}
