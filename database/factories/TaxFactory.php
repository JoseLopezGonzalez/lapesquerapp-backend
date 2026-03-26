<?php

namespace Database\Factories;

use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxFactory extends Factory
{
    protected $model = Tax::class;

    public function definition(): array
    {
        $rate = $this->faker->randomElement([0, 4, 10, 21]);

        return [
            'name' => $rate === 0 ? 'Exento' : "IVA {$rate}%",
            'rate' => $rate,
        ];
    }
}
