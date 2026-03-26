<?php

namespace Database\Factories;

use App\Models\Box;
use App\Models\ProductionInput;
use App\Models\ProductionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionInputFactory extends Factory
{
    protected $model = ProductionInput::class;

    public function definition(): array
    {
        $availableBoxId = Box::query()
            ->whereDoesntHave('productionInputs')
            ->value('id');

        return [
            'production_record_id' => ProductionRecord::query()->value('id') ?? ProductionRecord::factory(),
            'box_id' => $availableBoxId ?? Box::factory(),
        ];
    }
}
