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
        // Alinear métricas globales con la lógica del lote:
        // input = stock inputs, output = outputs de nodos finales.
        $globalTotals = $this->calculateGlobalTotals();
        $inputWeight = (float) ($globalTotals['totalInputWeight'] ?? 0);
        $outputWeight = (float) ($globalTotals['totalOutputWeight'] ?? 0);
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
            'totalInputBoxes' => (int) ($globalTotals['totalInputBoxes'] ?? 0),
            'totalOutputBoxes' => (int) ($globalTotals['totalOutputBoxes'] ?? 0),
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
            'closedBy' => $this->closed_by,
            'closureReason' => $this->closure_reason,
            'closedByUser' => $this->whenLoaded('closedByUser', fn () => $this->closedByUser ? [
                'id' => $this->closedByUser->id,
                'name' => $this->closedByUser->name,
            ] : null),
            'reopenedAt' => $this->reopened_at?->toIso8601String(),
            'reopenedBy' => $this->reopened_by,
            'reopenReason' => $this->reopen_reason,
            'reopenedByUser' => $this->whenLoaded('reopenedByUser', fn () => $this->reopenedByUser ? [
                'id' => $this->reopenedByUser->id,
                'name' => $this->reopenedByUser->name,
            ] : null),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
