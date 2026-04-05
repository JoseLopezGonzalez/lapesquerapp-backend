<?php

namespace App\Services\Production;

use App\Models\Box;
use App\Models\Production;
use App\Models\ProductionCost;
use App\Models\ProductionOutput;
use App\Models\ProductionOutputSource;

class ProductionCostResolver
{
    /** @var array<int, ?float> */
    private array $outputTotalCostCache = [];

    /** @var array<int, ?float> */
    private array $outputCostPerKgCache = [];

    /** @var array<int, array> */
    private array $outputCostBreakdownCache = [];

    /** @var array<int, ?float> */
    private array $boxCostPerKgCache = [];

    /** @var array<int, ?float> */
    private array $boxTotalCostCache = [];

    /** @var array<string, ?float> */
    private array $lotProductCostPerKgCache = [];

    /** @var array<int, \Illuminate\Support\Collection> */
    private array $productionFinalOutputsCache = [];

    /** @var array<int, true> */
    private array $outputCalculationStack = [];

    public function getOutputCostPerKg(ProductionOutput $output): ?float
    {
        if ($output->id !== null && array_key_exists($output->id, $this->outputCostPerKgCache)) {
            return $this->outputCostPerKgCache[$output->id];
        }

        if ($output->weight_kg <= 0) {
            return $this->rememberOutputCostPerKg($output, null);
        }

        $totalCost = $this->getOutputTotalCost($output);
        if ($totalCost === null) {
            return $this->rememberOutputCostPerKg($output, null);
        }

        return $this->rememberOutputCostPerKg($output, $totalCost / (float) $output->weight_kg);
    }

    public function getOutputTotalCost(ProductionOutput $output): ?float
    {
        if ($output->id !== null && array_key_exists($output->id, $this->outputTotalCostCache)) {
            return $this->outputTotalCostCache[$output->id];
        }

        if ($output->id !== null && isset($this->outputCalculationStack[$output->id])) {
            return null;
        }

        if ($output->id !== null) {
            $this->outputCalculationStack[$output->id] = true;
        }

        try {
            $materialsCost = $this->calculateOutputMaterialsCost($output);
            $processCost = $this->calculateOutputProcessCost($output);
            $productionCost = $this->calculateOutputProductionCost($output);
            $total = $materialsCost + $processCost + $productionCost;

            return $this->rememberOutputTotalCost($output, $total > 0 ? $total : null);
        } finally {
            if ($output->id !== null) {
                unset($this->outputCalculationStack[$output->id]);
            }
        }
    }

    public function getOutputCostBreakdown(ProductionOutput $output): array
    {
        if ($output->id !== null && array_key_exists($output->id, $this->outputCostBreakdownCache)) {
            return $this->outputCostBreakdownCache[$output->id];
        }

        $breakdown = [
            'materials' => [
                'total_cost' => 0,
                'cost_per_kg' => 0,
                'sources' => [],
            ],
            'process_costs' => [
                'production' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'labor' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'operational' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'packaging' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'total' => ['total_cost' => 0, 'cost_per_kg' => 0],
            ],
            'production_costs' => [
                'production' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'labor' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'operational' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'packaging' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'total' => ['total_cost' => 0, 'cost_per_kg' => 0],
            ],
            'total' => [
                'total_cost' => 0,
                'cost_per_kg' => 0,
            ],
        ];

        $output->loadMissing('sources');
        $materialsCost = $this->calculateOutputMaterialsCost($output);
        $breakdown['materials']['total_cost'] = $materialsCost;
        $breakdown['materials']['cost_per_kg'] = $output->weight_kg > 0 ? $materialsCost / $output->weight_kg : 0;

        foreach ($output->sources as $source) {
            $breakdown['materials']['sources'][] = [
                'source_type' => $source->source_type,
                'contributed_weight_kg' => $source->contributed_weight_kg,
                'contribution_percentage' => $source->contribution_percentage,
                'source_cost_per_kg' => $source->source_cost_per_kg,
                'source_total_cost' => $source->source_total_cost,
            ];
        }

        $record = $output->productionRecord;
        if ($record) {
            $processCosts = ProductionCost::where('production_record_id', $record->id)
                ->whereNull('production_id')
                ->get();

            $totalOutputWeight = $record->total_output_weight;
            if ($totalOutputWeight > 0) {
                $outputPercentage = ($output->weight_kg / $totalOutputWeight) * 100;

                foreach ($processCosts as $cost) {
                    $costType = $cost->cost_type;
                    $effectiveCost = $cost->effective_total_cost ?? 0;
                    $assignedCost = ($effectiveCost * $outputPercentage) / 100;

                    $breakdown['process_costs'][$costType]['total_cost'] += $assignedCost;
                    $breakdown['process_costs'][$costType]['breakdown'][] = [
                        'name' => $cost->name,
                        'total_cost' => $assignedCost,
                        'cost_per_kg' => $output->weight_kg > 0 ? $assignedCost / $output->weight_kg : 0,
                    ];
                }

                foreach (['production', 'labor', 'operational', 'packaging'] as $type) {
                    $breakdown['process_costs'][$type]['cost_per_kg'] = $output->weight_kg > 0
                        ? $breakdown['process_costs'][$type]['total_cost'] / $output->weight_kg
                        : 0;
                }

                $totalProcessCost = $breakdown['process_costs']['production']['total_cost'] +
                    $breakdown['process_costs']['labor']['total_cost'] +
                    $breakdown['process_costs']['operational']['total_cost'] +
                    $breakdown['process_costs']['packaging']['total_cost'];
                $breakdown['process_costs']['total']['total_cost'] = $totalProcessCost;
                $breakdown['process_costs']['total']['cost_per_kg'] = $output->weight_kg > 0
                    ? $totalProcessCost / $output->weight_kg
                    : 0;
            }
        }

        if ($this->isFinalOutput($output) && $record) {
            $production = $record->production;
            if ($production) {
                $productionCosts = ProductionCost::where('production_id', $production->id)
                    ->whereNull('production_record_id')
                    ->get();

                $finalOutputs = $this->getFinalOutputsOfProduction($production);
                $totalFinalOutputWeight = $finalOutputs->sum('weight_kg');

                if ($totalFinalOutputWeight > 0) {
                    $outputPercentage = ($output->weight_kg / $totalFinalOutputWeight) * 100;

                    foreach ($productionCosts as $cost) {
                        $costType = $cost->cost_type;
                        $effectiveCost = $cost->effective_total_cost ?? 0;
                        $assignedCost = ($effectiveCost * $outputPercentage) / 100;

                        $breakdown['production_costs'][$costType]['total_cost'] += $assignedCost;
                        $breakdown['production_costs'][$costType]['breakdown'][] = [
                            'name' => $cost->name,
                            'total_cost' => $assignedCost,
                            'cost_per_kg' => $output->weight_kg > 0 ? $assignedCost / $output->weight_kg : 0,
                        ];
                    }

                    foreach (['production', 'labor', 'operational', 'packaging'] as $type) {
                        $breakdown['production_costs'][$type]['cost_per_kg'] = $output->weight_kg > 0
                            ? $breakdown['production_costs'][$type]['total_cost'] / $output->weight_kg
                            : 0;
                    }

                    $totalProductionCost = $breakdown['production_costs']['production']['total_cost'] +
                        $breakdown['production_costs']['labor']['total_cost'] +
                        $breakdown['production_costs']['operational']['total_cost'] +
                        $breakdown['production_costs']['packaging']['total_cost'];
                    $breakdown['production_costs']['total']['total_cost'] = $totalProductionCost;
                    $breakdown['production_costs']['total']['cost_per_kg'] = $output->weight_kg > 0
                        ? $totalProductionCost / $output->weight_kg
                        : 0;
                }
            }
        }

        $totalCost = $materialsCost +
            $breakdown['process_costs']['total']['total_cost'] +
            $breakdown['production_costs']['total']['total_cost'];

        $breakdown['total']['total_cost'] = $totalCost;
        $breakdown['total']['cost_per_kg'] = $output->weight_kg > 0 ? $totalCost / $output->weight_kg : 0;

        if ($output->id !== null) {
            $this->outputCostBreakdownCache[$output->id] = $breakdown;
            $this->outputTotalCostCache[$output->id] = $totalCost > 0 ? $totalCost : null;
            $this->outputCostPerKgCache[$output->id] = $output->weight_kg > 0 && $totalCost > 0
                ? $totalCost / (float) $output->weight_kg
                : null;
        }

        return $breakdown;
    }

    public function getBoxCostPerKg(Box $box): ?float
    {
        if ($box->id !== null && array_key_exists($box->id, $this->boxCostPerKgCache)) {
            return $this->boxCostPerKgCache[$box->id];
        }

        $pallet = $box->pallet;
        if ($pallet && $pallet->reception_id) {
            $reception = $pallet->reception;
            $receptionProduct = $reception?->products()
                ->where('product_id', $box->article_id)
                ->where('lot', $box->lot)
                ->first();

            return $this->rememberBoxCostPerKg($box, $receptionProduct?->price);
        }

        if ($box->article_id === null || $box->lot === null || trim($box->lot) === '') {
            return $this->rememberBoxCostPerKg($box, null);
        }

        return $this->rememberBoxCostPerKg(
            $box,
            $this->getProductionLotProductCostPerKg((string) $box->lot, (int) $box->article_id)
        );
    }

    public function getBoxTotalCost(Box $box): ?float
    {
        if ($box->id !== null && array_key_exists($box->id, $this->boxTotalCostCache)) {
            return $this->boxTotalCostCache[$box->id];
        }

        $costPerKg = $this->getBoxCostPerKg($box);
        $totalCost = $costPerKg === null ? null : ((float) $box->net_weight * $costPerKg);

        if ($box->id !== null) {
            $this->boxTotalCostCache[$box->id] = $totalCost;
        }

        return $totalCost;
    }

    public function getProductionLotProductCostPerKg(string $lot, int $productId): ?float
    {
        $cacheKey = "{$lot}:{$productId}";
        if (array_key_exists($cacheKey, $this->lotProductCostPerKgCache)) {
            return $this->lotProductCostPerKgCache[$cacheKey];
        }

        $outputs = ProductionOutput::query()
            ->where('product_id', $productId)
            ->whereHas('productionRecord', function ($query) use ($lot) {
                $query->whereHas('production', function ($productionQuery) use ($lot) {
                    $productionQuery->where('lot', $lot);
                })
                    ->whereDoesntHave('inputs')
                    ->whereDoesntHave('children');
            })
            ->with([
                'productionRecord.production',
                'sources.product',
                'sources.productionOutputConsumption.productionOutput.product',
                'sources.productionOutputConsumption.productionOutput.productionRecord.production',
            ])
            ->get();

        if ($outputs->isEmpty()) {
            return $this->lotProductCostPerKgCache[$cacheKey] = null;
        }

        $totalWeight = 0.0;
        $totalCost = 0.0;

        foreach ($outputs as $output) {
            $weight = (float) ($output->weight_kg ?? 0);
            $costPerKg = $this->getOutputCostPerKg($output);

            if ($weight <= 0 || $costPerKg === null) {
                continue;
            }

            $totalWeight += $weight;
            $totalCost += $weight * $costPerKg;
        }

        if ($totalWeight <= 0) {
            return $this->lotProductCostPerKgCache[$cacheKey] = null;
        }

        return $this->lotProductCostPerKgCache[$cacheKey] = $totalCost / $totalWeight;
    }

    private function calculateOutputMaterialsCost(ProductionOutput $output): float
    {
        $total = 0.0;
        $output->loadMissing('sources');

        foreach ($output->sources as $source) {
            $sourceCost = $source->source_total_cost;
            if ($sourceCost !== null) {
                $total += $sourceCost;
            } elseif ($source->source_type === ProductionOutputSource::SOURCE_TYPE_PARENT_OUTPUT) {
                $source->loadMissing('productionOutputConsumption.productionOutput');
                $consumption = $source->productionOutputConsumption;
                if ($consumption && $consumption->productionOutput) {
                    $parentOutput = $consumption->productionOutput;
                    if ($parentOutput->id == $output->id) {
                        continue;
                    }

                    $parentTotalCost = $this->getOutputTotalCost($parentOutput);
                    if ($parentTotalCost !== null && $parentTotalCost > 0 && $parentOutput->weight_kg > 0) {
                        $parentCostPerKg = $parentTotalCost / (float) $parentOutput->weight_kg;
                        $sourceWeight = (float) ($source->contributed_weight_kg ?? 0);
                        if ($sourceWeight > 0) {
                            $total += $sourceWeight * $parentCostPerKg;
                        }
                    }
                }
            }
        }

        return $total;
    }

    private function calculateOutputProcessCost(ProductionOutput $output): float
    {
        $record = $output->productionRecord;
        if (! $record) {
            return 0.0;
        }

        $processCosts = ProductionCost::where('production_record_id', $record->id)
            ->whereNull('production_id')
            ->get();

        if ($processCosts->isEmpty()) {
            return 0.0;
        }

        $totalOutputWeight = $record->total_output_weight;
        if ($totalOutputWeight <= 0) {
            return 0.0;
        }

        $totalProcessCost = 0.0;
        foreach ($processCosts as $cost) {
            $totalProcessCost += $cost->effective_total_cost ?? 0;
        }

        $outputPercentage = ($output->weight_kg / $totalOutputWeight) * 100;

        return ($totalProcessCost * $outputPercentage) / 100;
    }

    private function calculateOutputProductionCost(ProductionOutput $output): float
    {
        $record = $output->productionRecord;
        if (! $record) {
            return 0.0;
        }

        $production = $record->production;
        if (! $production || ! $this->isFinalOutput($output)) {
            return 0.0;
        }

        $productionCosts = ProductionCost::where('production_id', $production->id)
            ->whereNull('production_record_id')
            ->get();

        if ($productionCosts->isEmpty()) {
            return 0.0;
        }

        $finalOutputs = $this->getFinalOutputsOfProduction($production);
        $totalFinalOutputWeight = $finalOutputs->sum('weight_kg');
        if ($totalFinalOutputWeight <= 0) {
            return 0.0;
        }

        $totalProductionCost = 0.0;
        foreach ($productionCosts as $cost) {
            $totalProductionCost += $cost->effective_total_cost ?? 0;
        }

        $outputPercentage = ($output->weight_kg / $totalFinalOutputWeight) * 100;

        return ($totalProductionCost * $outputPercentage) / 100;
    }

    private function isFinalOutput(ProductionOutput $output): bool
    {
        $record = $output->productionRecord;

        return $record ? $record->isFinal() : false;
    }

    private function getFinalOutputsOfProduction(Production $production)
    {
        if ($production->id !== null && array_key_exists($production->id, $this->productionFinalOutputsCache)) {
            return $this->productionFinalOutputsCache[$production->id];
        }

        $allRecords = $production->records()
            ->with(['inputs', 'children', 'outputs'])
            ->get();

        $finalRecords = $allRecords->filter(function ($record) {
            return $record->isFinal();
        });

        $finalOutputs = collect();
        foreach ($finalRecords as $record) {
            $finalOutputs = $finalOutputs->merge($record->outputs);
        }

        if ($production->id !== null) {
            $this->productionFinalOutputsCache[$production->id] = $finalOutputs;
        }

        return $finalOutputs;
    }

    private function rememberOutputTotalCost(ProductionOutput $output, ?float $value): ?float
    {
        if ($output->id !== null) {
            $this->outputTotalCostCache[$output->id] = $value;
        }

        return $value;
    }

    private function rememberOutputCostPerKg(ProductionOutput $output, ?float $value): ?float
    {
        if ($output->id !== null) {
            $this->outputCostPerKgCache[$output->id] = $value;
        }

        return $value;
    }

    private function rememberBoxCostPerKg(Box $box, ?float $value): ?float
    {
        if ($box->id !== null) {
            $this->boxCostPerKgCache[$box->id] = $value;
        }

        return $value;
    }
}
