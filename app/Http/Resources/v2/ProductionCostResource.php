<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionCostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'productionRecordId' => $this->production_record_id,
            'productionId' => $this->production_id,
            'costCatalogId' => $this->cost_catalog_id,
            'costCatalog' => $this->when(
                $this->costCatalog && $request->has('include_catalog'),
                fn() => new CostCatalogResource($this->costCatalog)
            ),
            'costType' => $this->cost_type,
            'name' => $this->name,
            'description' => $this->description,
            'totalCost' => $this->total_cost ? (float) $this->total_cost : null,
            'costPerKg' => $this->cost_per_kg ? (float) $this->cost_per_kg : null,
            'distributionUnit' => $this->distribution_unit,
            'costDate' => $this->cost_date?->toDateString(),
            'effectiveTotalCost' => $this->effective_total_cost,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
