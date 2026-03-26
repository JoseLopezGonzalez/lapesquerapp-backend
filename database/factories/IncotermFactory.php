<?php

namespace Database\Factories;

use App\Models\Incoterm;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncotermFactory extends Factory
{
    protected $model = Incoterm::class;

    public function definition(): array
    {
        return $this->faker->randomElement([
            ['code' => 'EXW', 'description' => 'Ex Works'],
            ['code' => 'FCA', 'description' => 'Free Carrier'],
            ['code' => 'CPT', 'description' => 'Carriage Paid To'],
            ['code' => 'CIP', 'description' => 'Carriage and Insurance Paid To'],
            ['code' => 'DAP', 'description' => 'Delivered at Place'],
            ['code' => 'DDP', 'description' => 'Delivered Duty Paid'],
        ]);
    }
}
