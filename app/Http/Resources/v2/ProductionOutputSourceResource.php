<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOutputSourceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $productionInput = $this->relationLoaded('productionInput') ? $this->productionInput : null;
        $productionOutputConsumption = $this->relationLoaded('productionOutputConsumption') ? $this->productionOutputConsumption : null;

        return [
            'id' => $this->id,
            'productionOutputId' => $this->production_output_id,
            'sourceType' => $this->source_type,
            'productionInputId' => $this->production_input_id,
            'productionInput' => $this->when(
                $productionInput && $request->has('include_input'),
                fn() => new ProductionInputResource($productionInput)
            ),
            'productionOutputConsumptionId' => $this->production_output_consumption_id,
            'productionOutputConsumption' => $this->when(
                $productionOutputConsumption && $request->has('include_consumption'),
                fn() => new ProductionOutputConsumptionResource($productionOutputConsumption)
            ),
            'contributedWeightKg' => $this->contributed_weight_kg ? (float) $this->contributed_weight_kg : null,
            'contributedBoxes' => $this->contributed_boxes,
            'contributionPercentage' => $this->contribution_percentage ? (float) $this->contribution_percentage : null,
            'sourceCostPerKg' => $this->source_cost_per_kg,
            'sourceTotalCost' => $this->source_total_cost,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
