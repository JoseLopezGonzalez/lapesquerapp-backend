<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductionOutput;
use App\Models\ProductionOutputConsumption;
use App\Models\ProductionOutputSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionOutputSourceFactory extends Factory
{
    protected $model = ProductionOutputSource::class;

    public function definition(): array
    {
        return [
            'production_output_id' => ProductionOutput::query()->value('id') ?? ProductionOutput::factory(),
            'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT,
            'product_id' => Product::query()->value('id') ?? Product::factory(),
            'production_output_consumption_id' => null,
            'contributed_weight_kg' => $this->faker->randomFloat(2, 1, 20),
            'contributed_boxes' => $this->faker->numberBetween(0, 3),
        ];
    }

    public function stockProduct(Product|int $product): static
    {
        $productId = $product instanceof Product ? $product->id : $product;

        return $this->state(fn () => [
            'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT,
            'product_id' => $productId,
            'production_output_consumption_id' => null,
        ]);
    }

    public function parentOutput(ProductionOutputConsumption|int $consumption): static
    {
        $consumptionId = $consumption instanceof ProductionOutputConsumption ? $consumption->id : $consumption;

        return $this->state(fn () => [
            'source_type' => ProductionOutputSource::SOURCE_TYPE_PARENT_OUTPUT,
            'product_id' => null,
            'production_output_consumption_id' => $consumptionId,
        ]);
    }
}
