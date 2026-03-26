<?php

namespace Database\Factories;

use App\Models\Transport;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransportFactory extends Factory
{
    protected $model = Transport::class;

    public function definition(): array
    {
        return [
            'name' => 'Transportes ' . $this->faker->unique()->company(),
            'vat_number' => 'B' . $this->faker->unique()->numerify('########'),
            'address' => $this->faker->address(),
            'emails' => $this->faker->companyEmail() . ';',
        ];
    }
}
