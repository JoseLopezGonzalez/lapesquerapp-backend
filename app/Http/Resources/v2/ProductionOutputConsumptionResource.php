<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOutputConsumptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $productionOutput = $this->relationLoaded('productionOutput') ? $this->productionOutput : null;
        $productionOutputProduct = $productionOutput && $productionOutput->relationLoaded('product')
            ? $productionOutput->product
            : null;
        $parentRecord = $productionOutput && $productionOutput->relationLoaded('productionRecord')
            ? $productionOutput->productionRecord
            : null;
        $parentProcess = $parentRecord && $parentRecord->relationLoaded('process')
            ? $parentRecord->process
            : null;
        $costPerKg = $productionOutput && $productionOutput->cost_per_kg !== null
            ? (float) $productionOutput->cost_per_kg
            : null;
        $consumedWeightKg = (float) ($this->consumed_weight_kg ?? 0);
        $consumedTotalCost = $costPerKg !== null
            ? $consumedWeightKg * $costPerKg
            : null;

        return [
            'id' => $this->id,
            'productionRecordId' => $this->production_record_id,
            'productionOutputId' => $this->production_output_id,
            'consumedWeightKg' => $consumedWeightKg,
            'consumedBoxes' => $this->consumed_boxes,
            'notes' => $this->notes,
            'costPerKg' => $costPerKg,
            'totalCost' => $consumedTotalCost,
            
            // Información del output consumido
            'productionOutput' => $this->whenLoaded('productionOutput', function () {
                return new ProductionOutputResource($this->productionOutput);
            }),
            
            // Información del producto
            'product' => $this->when($productionOutputProduct, function () use ($productionOutputProduct) {
                return [
                    'id' => $productionOutputProduct->id,
                    'name' => $productionOutputProduct->name,
                ];
            }),
            
            // Información del proceso que consume
            'productionRecord' => $this->whenLoaded('productionRecord', function () {
                return new ProductionRecordResource($this->productionRecord);
            }),
            
            // Información del proceso padre (del output)
            'parentRecord' => $this->when($parentRecord, function () use ($parentRecord, $parentProcess) {
                return [
                    'id' => $parentRecord->id,
                    'process' => $parentProcess ? [
                        'id' => $parentProcess->id,
                        'name' => $parentProcess->name,
                    ] : null,
                ];
            }),
            
            // Valores calculados
            'isComplete' => $this->isComplete(),
            'isPartial' => $this->isPartial(),
            'weightConsumptionPercentage' => round($this->weight_consumption_percentage, 2),
            'boxesConsumptionPercentage' => round($this->boxes_consumption_percentage, 2),
            
            // Información del output original para referencia
            'outputTotalWeight' => $this->when($productionOutput, function () use ($productionOutput) {
                return $productionOutput->weight_kg ?? 0;
            }),
            'outputTotalBoxes' => $this->when($productionOutput, function () use ($productionOutput) {
                return $productionOutput->boxes ?? 0;
            }),
            'outputAvailableWeight' => $this->when($productionOutput, function () use ($productionOutput) {
                return $productionOutput->available_weight_kg ?? 0;
            }),
            'outputAvailableBoxes' => $this->when($productionOutput, function () use ($productionOutput) {
                return $productionOutput->available_boxes ?? 0;
            }),
            
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
