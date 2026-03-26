<?php

namespace Database\Factories;

use App\Models\ProductionOutput;
use App\Models\ProductionOutputConsumption;
use App\Models\ProductionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionOutputConsumptionFactory extends Factory
{
    protected $model = ProductionOutputConsumption::class;

    public function definition(): array
    {
        return [
            'production_record_id' => ProductionRecord::query()->whereNotNull('parent_record_id')->value('id') ?? ProductionRecord::factory(),
            'production_output_id' => ProductionOutput::query()->value('id') ?? ProductionOutput::factory(),
            'consumed_weight_kg' => $this->faker->randomFloat(2, 1, 25),
            'consumed_boxes' => $this->faker->numberBetween(0, 5),
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }
}
