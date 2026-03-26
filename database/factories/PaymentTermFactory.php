<?php

namespace Database\Factories;

use App\Models\PaymentTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentTermFactory extends Factory
{
    protected $model = PaymentTerm::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['Contado', '30 días', '60 días', '90 días', 'Transferencia']),
        ];
    }
}
