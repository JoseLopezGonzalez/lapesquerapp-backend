<?php

namespace Database\Seeders;

use App\Models\CostCatalog;
use Database\Seeders\Concerns\SeedsTenantProductionData;
use Illuminate\Database\Seeder;

class TenantProductionCostsSeeder extends Seeder
{
    use SeedsTenantProductionData;

    public function run(): void
    {
        $this->productionCostCatalog('Coste línea fileteado', CostCatalog::COST_TYPE_PRODUCTION, CostCatalog::DEFAULT_UNIT_PER_KG);
        $this->productionCostCatalog('Mano de obra turno mañana', CostCatalog::COST_TYPE_LABOR, CostCatalog::DEFAULT_UNIT_TOTAL);
        $this->productionCostCatalog('Material de envasado', CostCatalog::COST_TYPE_PACKAGING, CostCatalog::DEFAULT_UNIT_PER_KG);
        $this->productionCostCatalog('Coste operativo frío', CostCatalog::COST_TYPE_OPERATIONAL, CostCatalog::DEFAULT_UNIT_TOTAL);
    }
}
