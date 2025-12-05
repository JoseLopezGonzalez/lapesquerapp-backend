<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calcular diferencia para determinar si hay pérdida o ganancia
        $inputWeight = $this->total_input_weight;
        $outputWeight = $this->total_output_weight;
        $difference = $inputWeight - $outputWeight;
        
        // Calcular waste (solo si hay pérdida)
        $waste = $difference > 0 ? round($difference, 2) : 0;
        $wastePercentage = ($difference > 0 && $inputWeight > 0) 
            ? round(($difference / $inputWeight) * 100, 2) 
            : 0;
        
        // Calcular yield (solo si hay ganancia)
        $yield = $difference < 0 ? round(abs($difference), 2) : 0;
        $yieldPercentage = ($difference < 0 && $inputWeight > 0) 
            ? round((abs($difference) / $inputWeight) * 100, 2) 
            : 0;
        
        return [
            'id' => $this->id,
            'productionId' => $this->production_id,
            'production' => $this->whenLoaded('production', function () {
                return [
                    'id' => $this->production->id,
                    'lot' => $this->production->lot,
                    'openedAt' => $this->production->opened_at,
                    'closedAt' => $this->production->closed_at,
                ];
            }),
            'parentRecordId' => $this->parent_record_id,
            'parent' => $this->whenLoaded('parent', function () {
                return [
                    'id' => $this->parent->id,
                    'process' => $this->parent->process ? [
                        'id' => $this->parent->process->id,
                        'name' => $this->parent->process->name,
                    ] : null,
                ];
            }),
            'processId' => $this->process_id,
            'process' => $this->whenLoaded('process', function () {
                return [
                    'id' => $this->process->id,
                    'name' => $this->process->name,
                    'type' => $this->process->type,
                ];
            }),
            'startedAt' => $this->started_at?->toIso8601String(),
            'finishedAt' => $this->finished_at?->toIso8601String(),
            'notes' => $this->notes,
            'isRoot' => $this->isRoot(),
            'isFinal' => $this->isFinal(),
            'isCompleted' => $this->isCompleted(),
            'totalInputWeight' => $inputWeight,
            'totalOutputWeight' => $outputWeight,
            'totalInputBoxes' => $this->total_input_boxes,
            'totalOutputBoxes' => $this->total_output_boxes,
            'waste' => $waste,
            'wastePercentage' => $wastePercentage,
            'yield' => $yield,
            'yieldPercentage' => $yieldPercentage,
            'inputs' => ProductionInputResource::collection($this->whenLoaded('inputs')),
            'outputs' => ProductionOutputResource::collection($this->whenLoaded('outputs')),
            'parentOutputConsumptions' => ProductionOutputConsumptionResource::collection($this->whenLoaded('parentOutputConsumptions')),
            'children' => ProductionRecordResource::collection($this->whenLoaded('children')),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
