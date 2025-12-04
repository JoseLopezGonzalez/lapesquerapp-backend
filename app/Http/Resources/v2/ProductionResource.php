<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calcular diferencia para determinar si hay pérdida o ganancia (igual que ProductionRecord)
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
            'lot' => $this->lot,
            'speciesId' => $this->species_id,
            'species' => $this->whenLoaded('species', function () {
                return $this->species ? [
                    'id' => $this->species->id,
                    'name' => $this->species->name,
                ] : null;
            }),
            'captureZoneId' => $this->capture_zone_id,
            'captureZone' => $this->whenLoaded('captureZone', function () {
                return $this->captureZone ? [
                    'id' => $this->captureZone->id,
                    'name' => $this->captureZone->name,
                ] : null;
            }),
            'notes' => $this->notes,
            'openedAt' => $this->opened_at?->toIso8601String(),
            'closedAt' => $this->closed_at?->toIso8601String(),
            'isOpen' => $this->isOpen(),
            'isClosed' => $this->isClosed(),
            'date' => $this->date?->format('Y-m-d'),
            'diagramData' => $this->when($request->has('include_diagram'), function () {
                return $this->getDiagramData();
            }),
            'totals' => $this->when($request->has('include_totals'), function () {
                return $this->calculateGlobalTotals();
            }),
            // Totales básicos (siempre incluidos para consistencia con ProductionRecord)
            'totalInputWeight' => round($inputWeight, 2),
            'totalOutputWeight' => round($outputWeight, 2),
            'totalInputBoxes' => $this->total_input_boxes,
            'totalOutputBoxes' => $this->total_output_boxes,
            // Merma y rendimiento (igual que ProductionRecord)
            'waste' => $waste,
            'wastePercentage' => $wastePercentage,
            'yield' => $yield,
            'yieldPercentage' => $yieldPercentage,
            'records' => $this->whenLoaded('records', function () {
                return $this->records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'processId' => $record->process_id,
                        'startedAt' => $record->started_at?->toIso8601String(),
                        'finishedAt' => $record->finished_at?->toIso8601String(),
                    ];
                });
            }),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
