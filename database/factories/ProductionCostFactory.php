<?php

namespace Database\Factories;

use App\Models\CostCatalog;
use App\Models\Production;
use App\Models\ProductionCost;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionCostFactory extends Factory
{
    protected $model = ProductionCost::class;

    public function definition(): array
    {
        return [
            'production_record_id' => null,
            'production_id' => Production::query()->value('id') ?? Production::factory(),
            'cost_catalog_id' => CostCatalog::query()->value('id') ?? CostCatalog::factory(),
            'cost_type' => ProductionCost::COST_TYPE_PRODUCTION,
            'name' => 'Coste de producción',
            'description' => $this->faker->optional(0.35)->sentence(),
            'total_cost' => $this->faker->randomFloat(2, 10, 500),
            'cost_per_kg' => null,
            'distribution_unit' => 'per_kg',
            'cost_date' => now()->toDateString(),
        ];
    }

    public function forProduction(Production|int $production): static
    {
        $productionId = $production instanceof Production ? $production->id : $production;

        return $this->state(fn () => [
            'production_id' => $productionId,
            'production_record_id' => null,
        ]);
    }

    public function forRecord(\App\Models\ProductionRecord|int $record): static
    {
        $productionRecord = $record instanceof \App\Models\ProductionRecord ? $record : \App\Models\ProductionRecord::query()->findOrFail($record);

        return $this->state(fn () => [
            'production_record_id' => $productionRecord->id,
            'production_id' => null,
        ]);
    }

    public function totalCost(): static
    {
        return $this->state(fn () => [
            'total_cost' => $this->faker->randomFloat(2, 20, 300),
            'cost_per_kg' => null,
        ]);
    }

    public function perKg(): static
    {
        return $this->state(fn () => [
            'total_cost' => null,
            'cost_per_kg' => $this->faker->randomFloat(2, 0.25, 4.5),
        ]);
    }
}
