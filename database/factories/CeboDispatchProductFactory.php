<?php

namespace Database\Factories;

use App\Models\CeboDispatch;
use App\Models\CeboDispatchProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class CeboDispatchProductFactory extends Factory
{
    protected $model = CeboDispatchProduct::class;

    public function definition(): array
    {
        return [
            'dispatch_id' => CeboDispatch::query()->value('id') ?? CeboDispatch::factory(),
            'product_id' => Product::query()->value('id') ?? Product::factory(),
            'net_weight' => $this->faker->randomFloat(2, 5, 200),
            'price' => $this->faker->optional(0.6)->randomFloat(3, 0.5, 7),
        ];
    }
}
