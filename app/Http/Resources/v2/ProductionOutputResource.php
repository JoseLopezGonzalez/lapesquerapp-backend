<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOutputResource extends JsonResource
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
            'productId' => $this->product_id,
            'product' => $this->whenLoaded('product', function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                ] : null;
            }),
            'lotId' => $this->lot_id,
            'boxes' => $this->boxes,
            'weightKg' => (float) $this->weight_kg,
            'averageWeightPerBox' => (float) $this->average_weight_per_box,
            
            // ✨ NUEVOS CAMPOS DE COSTE
            'costPerKg' => $this->cost_per_kg,
            'totalCost' => $this->total_cost,
            'costBreakdown' => $this->when(
                $request->has('include_cost_breakdown'),
                fn() => $this->cost_breakdown
            ),
            'sources' => ProductionOutputSourceResource::collection($this->whenLoaded('sources')),
            
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
