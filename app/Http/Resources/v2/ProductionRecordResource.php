<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionRecordResource extends JsonResource
{
    protected function hasLoadedInputCostData(): bool
    {
        return $this->relationLoaded('inputs') || $this->relationLoaded('parentOutputConsumptions');
    }

    protected function shouldIncludeNodeCosts(Request $request): bool
    {
        return $request->routeIs('production-records.tree')
            || $request->is('api/v2/production-records/*/tree')
            || $request->has('include_costs');
    }

    protected function buildInputCostsSummary(): array
    {
        $inputs = $this->relationLoaded('inputs') ? $this->inputs : collect();
        $parentConsumptions = $this->relationLoaded('parentOutputConsumptions') ? $this->parentOutputConsumptions : collect();

        $stockProducts = $inputs
            ->filter(fn ($input) => $input->box && $input->box->relationLoaded('product') && $input->box->product)
            ->groupBy(fn ($input) => (int) $input->box->product->id)
            ->map(function ($groupedInputs) {
                $firstInput = $groupedInputs->first();
                $product = $firstInput?->box?->product;
                $totalWeight = (float) $groupedInputs->sum(fn ($input) => (float) ($input->box->net_weight ?? 0));
                $totalCost = (float) $groupedInputs->sum(fn ($input) => (float) ($input->box->total_cost ?? 0));

                return [
                    'productId' => $product?->id,
                    'product' => $product ? [
                        'id' => $product->id,
                        'name' => $product->name,
                    ] : null,
                    'inputCount' => $groupedInputs->count(),
                    'totalWeight' => $totalWeight,
                    'totalCost' => $totalCost,
                    'costPerKg' => $totalWeight > 0 ? $totalCost / $totalWeight : null,
                ];
            })
            ->values();

        $parentProducts = $parentConsumptions
            ->filter(fn ($consumption) => $consumption->productionOutput && $consumption->productionOutput->relationLoaded('product') && $consumption->productionOutput->product)
            ->groupBy(fn ($consumption) => (int) $consumption->productionOutput->product->id)
            ->map(function ($groupedConsumptions) {
                $firstConsumption = $groupedConsumptions->first();
                $output = $firstConsumption?->productionOutput;
                $product = $output?->product;
                $totalWeight = (float) $groupedConsumptions->sum(fn ($consumption) => (float) ($consumption->consumed_weight_kg ?? 0));
                $totalCost = (float) $groupedConsumptions->sum(function ($consumption) {
                    $outputCostPerKg = $consumption->productionOutput?->cost_per_kg;

                    return $outputCostPerKg !== null
                        ? (float) $consumption->consumed_weight_kg * (float) $outputCostPerKg
                        : 0;
                });

                return [
                    'productId' => $product?->id,
                    'product' => $product ? [
                        'id' => $product->id,
                        'name' => $product->name,
                    ] : null,
                    'consumptionCount' => $groupedConsumptions->count(),
                    'totalWeight' => $totalWeight,
                    'totalCost' => $totalCost,
                    'costPerKg' => $totalWeight > 0 ? $totalCost / $totalWeight : null,
                ];
            })
            ->values();

        $totalStockWeight = (float) $stockProducts->sum('totalWeight');
        $totalStockCost = (float) $stockProducts->sum('totalCost');
        $totalParentWeight = (float) $parentProducts->sum('totalWeight');
        $totalParentCost = (float) $parentProducts->sum('totalCost');
        $totalInputWeight = $totalStockWeight + $totalParentWeight;
        $totalInputCost = $totalStockCost + $totalParentCost;

        return [
            'stockProducts' => $stockProducts,
            'parentProducts' => $parentProducts,
            'totals' => [
                'stock' => [
                    'count' => $stockProducts->count(),
                    'totalWeight' => $totalStockWeight,
                    'totalCost' => $totalStockCost,
                    'averageCostPerKg' => $totalStockWeight > 0 ? $totalStockCost / $totalStockWeight : null,
                ],
                'parent' => [
                    'count' => $parentProducts->count(),
                    'totalWeight' => $totalParentWeight,
                    'totalCost' => $totalParentCost,
                    'averageCostPerKg' => $totalParentWeight > 0 ? $totalParentCost / $totalParentWeight : null,
                ],
                'combined' => [
                    'totalWeight' => $totalInputWeight,
                    'totalCost' => $totalInputCost,
                    'averageCostPerKg' => $totalInputWeight > 0 ? $totalInputCost / $totalInputWeight : null,
                ],
            ],
        ];
    }

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
                    'process' => $this->parent->relationLoaded('process') && $this->parent->process ? [
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
            'inputCostsSummary' => $this->when(
                $this->hasLoadedInputCostData(),
                fn () => $this->buildInputCostsSummary()
            ),
            'children' => ProductionRecordResource::collection($this->whenLoaded('children')),
            'costs' => $this->when(
                $this->shouldIncludeNodeCosts($request),
                fn () => $this->calculateNodeCosts()
            ),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
