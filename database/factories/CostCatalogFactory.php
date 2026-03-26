<?php

namespace Database\Factories;

use App\Models\CostCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

class CostCatalogFactory extends Factory
{
    protected $model = CostCatalog::class;

    public function definition(): array
    {
        return [
            'name' => 'Coste ' . $this->faker->unique()->word(),
            'cost_type' => $this->faker->randomElement([
                CostCatalog::COST_TYPE_PRODUCTION,
                CostCatalog::COST_TYPE_LABOR,
                CostCatalog::COST_TYPE_OPERATIONAL,
                CostCatalog::COST_TYPE_PACKAGING,
            ]),
            'description' => $this->faker->optional(0.4)->sentence(),
            'default_unit' => $this->faker->randomElement([
                CostCatalog::DEFAULT_UNIT_TOTAL,
                CostCatalog::DEFAULT_UNIT_PER_KG,
            ]),
            'is_active' => true,
        ];
    }
}
