<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderAuxiliaryLine;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderAuxiliaryLineFactory extends Factory
{
    protected $model = OrderAuxiliaryLine::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::query()->value('id') ?? Order::factory(),
            'auxiliary_product_id' => null,
            'description' => $this->faker->words(2, true),
            'quantity' => $this->faker->randomFloat(3, 1, 500),
            'unit' => $this->faker->randomElement(['ud', 'kg', 'saco']),
            'unit_price' => $this->faker->randomFloat(4, 0.05, 15),
            'tax_id' => $this->faker->optional(0.85)->passthrough(Tax::query()->value('id') ?? Tax::factory()),
        ];
    }
}
