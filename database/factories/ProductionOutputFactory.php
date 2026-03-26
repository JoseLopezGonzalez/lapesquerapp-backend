<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductionOutput;
use App\Models\ProductionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionOutputFactory extends Factory
{
    protected $model = ProductionOutput::class;

    public function definition(): array
    {
        return [
            'production_record_id' => ProductionRecord::query()->value('id') ?? ProductionRecord::factory(),
            'product_id' => Product::query()->value('id') ?? Product::factory(),
            'lot_id' => 'OUT-' . $this->faker->bothify('#####'),
            'boxes' => $this->faker->numberBetween(0, 20),
            'weight_kg' => $this->faker->randomFloat(2, 5, 250),
        ];
    }
}
