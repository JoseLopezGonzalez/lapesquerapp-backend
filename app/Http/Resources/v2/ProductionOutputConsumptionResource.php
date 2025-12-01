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
        return [
            'id' => $this->id,
            'productionRecordId' => $this->production_record_id,
            'productionOutputId' => $this->production_output_id,
            'consumedWeightKg' => $this->consumed_weight_kg,
            'consumedBoxes' => $this->consumed_boxes,
            'notes' => $this->notes,
            
            // Información del output consumido
            'productionOutput' => $this->whenLoaded('productionOutput', function () {
                return new ProductionOutputResource($this->productionOutput);
            }),
            
            // Información del producto
            'product' => $this->when($this->productionOutput && $this->productionOutput->product, function () {
                return [
                    'id' => $this->productionOutput->product->id,
                    'name' => $this->productionOutput->product->name,
                ];
            }),
            
            // Información del proceso que consume
            'productionRecord' => $this->whenLoaded('productionRecord', function () {
                return new ProductionRecordResource($this->productionRecord);
            }),
            
            // Información del proceso padre (del output)
            'parentRecord' => $this->when($this->productionOutput && $this->productionOutput->productionRecord, function () {
                return [
                    'id' => $this->productionOutput->productionRecord->id,
                    'process' => $this->productionOutput->productionRecord->process ? [
                        'id' => $this->productionOutput->productionRecord->process->id,
                        'name' => $this->productionOutput->productionRecord->process->name,
                    ] : null,
                ];
            }),
            
            // Valores calculados
            'isComplete' => $this->isComplete(),
            'isPartial' => $this->isPartial(),
            'weightConsumptionPercentage' => round($this->weight_consumption_percentage, 2),
            'boxesConsumptionPercentage' => round($this->boxes_consumption_percentage, 2),
            
            // Información del output original para referencia
            'outputTotalWeight' => $this->when($this->productionOutput, function () {
                return $this->productionOutput->weight_kg ?? 0;
            }),
            'outputTotalBoxes' => $this->when($this->productionOutput, function () {
                return $this->productionOutput->boxes ?? 0;
            }),
            'outputAvailableWeight' => $this->when($this->productionOutput, function () {
                return $this->productionOutput->available_weight_kg ?? 0;
            }),
            'outputAvailableBoxes' => $this->when($this->productionOutput, function () {
                return $this->productionOutput->available_boxes ?? 0;
            }),
            
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}

