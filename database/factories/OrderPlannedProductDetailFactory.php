<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderPlannedProductDetailFactory extends Factory
{
    protected $model = OrderPlannedProductDetail::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::query()->value('id') ?? Order::factory(),
            'product_id' => Product::query()->value('id') ?? Product::factory(),
            'tax_id' => $this->faker->optional(0.85)->passthrough(Tax::query()->value('id') ?? Tax::factory()),
            'quantity' => $this->faker->randomFloat(3, 1, 250),
            'boxes' => $this->faker->numberBetween(1, 30),
            'unit_price' => $this->faker->randomFloat(2, 2, 25),
        ];
    }
}
