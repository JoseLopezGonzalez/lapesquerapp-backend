<?php

namespace Database\Factories;

use App\Models\CustomsBroker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomsBroker>
 */
class CustomsBrokerFactory extends Factory
{
    protected $model = CustomsBroker::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Customs Brokers',
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
        ];
    }
}
