<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\RawMaterialReception;
use App\Models\RawMaterialReceptionProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class RawMaterialReceptionProductFactory extends Factory
{
    protected $model = RawMaterialReceptionProduct::class;

    public function definition(): array
    {
        return [
            'reception_id' => RawMaterialReception::query()->value('id') ?? RawMaterialReception::factory(),
            'product_id' => Product::query()->value('id') ?? Product::factory(),
            'lot' => $this->faker->optional(0.75)->passthrough(
                $this->faker->dateTimeBetween('-2 months', 'now')->format('dmy')
                . $this->faker->optional(0.25)->passthrough('OCC' . $this->faker->numerify('#####'))
            ),
            'net_weight' => $this->faker->randomFloat(2, 2, 120),
            'price' => $this->faker->optional(0.7)->randomFloat(3, 1, 14),
        ];
    }
}
