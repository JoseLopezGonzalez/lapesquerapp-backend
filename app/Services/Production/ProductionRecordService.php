<?php

namespace App\Services\Production;

use App\Models\ProductionOutput;
use App\Models\ProductionOutputConsumption;
use App\Models\ProductionOutputSource;
use App\Models\ProductionRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductionRecordService
{
    /**
     * List production records with filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionRecord::query();
        $query->with([
            'production',
            'parent.process',
            'process',
            'inputs.box.product',
            'inputs.box.palletBox',
            'inputs.box.product.species',
            'inputs.box.product.captureZone',
            'outputs.product',
            'outputs.sources.product',
            'outputs.sources.productionOutputConsumption.productionOutput.product',
            'outputs.sources.productionOutputConsumption.productionOutput.productionRecord.production',
            'outputs.productionRecord.production',
        ]);

        if (isset($filters['production_id'])) {
            $query->where('production_id', $filters['production_id']);
        }

        $rootOnly = filter_var($filters['root_only'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($rootOnly === true) {
            $query->whereNull('parent_record_id');
        }

        if (isset($filters['parent_record_id'])) {
            $query->where('parent_record_id', $filters['parent_record_id']);
        }

        if (isset($filters['process_id'])) {
            $query->where('process_id', $filters['process_id']);
        }

        if (isset($filters['completed'])) {
            if ($filters['completed'] === 'true' || $filters['completed'] === true) {
                $query->whereNotNull('finished_at');
            } else {
                $query->whereNull('finished_at');
            }
        }

        $query->orderBy('started_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Create a new production record
     */
    public function create(array $data): ProductionRecord
    {
        $record = ProductionRecord::create($data);
        $record->load([
            'production',
            'parent.process',
            'process',
            'inputs.box.product',
            'inputs.box.palletBox',
            'inputs.box.product.species',
            'inputs.box.product.captureZone',
            'outputs.product',
            'outputs.sources.product',
            'outputs.sources.productionOutputConsumption.productionOutput.product',
            'outputs.sources.productionOutputConsumption.productionOutput.productionRecord.production',
            'outputs.productionRecord.production',
        ]);

        return $record;
    }

    /**
     * Update a production record
     */
    public function update(ProductionRecord $record, array $data): ProductionRecord
    {
        $record->update($data);
        $record->load([
            'production',
            'parent.process',
            'process',
            'inputs.box.product',
            'inputs.box.palletBox',
            'inputs.box.product.species',
            'inputs.box.product.captureZone',
            'outputs.product',
            'outputs.sources.product',
            'outputs.sources.productionOutputConsumption.productionOutput.product',
            'outputs.sources.productionOutputConsumption.productionOutput.productionRecord.production',
            'outputs.productionRecord.production',
        ]);

        return $record;
    }

    /**
     * Delete a production record
     */
    public function delete(ProductionRecord $record): bool
    {
        // Validar si el proceso tiene inputs o outputs antes de eliminar
        if ($record->inputs()->exists()) {
            throw new \Exception('No se puede eliminar el proceso porque tiene entradas (inputs) asociadas. Debe eliminar las entradas primero.');
        }

        if ($record->outputs()->exists()) {
            throw new \Exception('No se puede eliminar el proceso porque tiene salidas (outputs) asociadas. Debe eliminar las salidas primero.');
        }

        // Validar si tiene procesos hijos
        if ($record->children()->exists()) {
            throw new \Exception('No se puede eliminar el proceso porque tiene procesos hijos asociados. Debe eliminar los procesos hijos primero.');
        }

        return $record->delete();
    }

    /**
     * Finish a production record
     */
    public function finish(ProductionRecord $record): ProductionRecord
    {
        if ($record->finished_at) {
            throw new \Exception('El proceso ya está finalizado.');
        }

        $record->update(['finished_at' => now('UTC')]);
        $record->load([
            'production',
            'parent.process',
            'process',
            'inputs.box.product',
            'inputs.box.palletBox',
            'inputs.box.product.species',
            'inputs.box.product.captureZone',
            'outputs.product',
            'outputs.sources.product',
            'outputs.sources.productionOutputConsumption.productionOutput.product',
            'outputs.sources.productionOutputConsumption.productionOutput.productionRecord.production',
            'outputs.productionRecord.production',
        ]);

        return $record;
    }

    /**
     * Sync outputs for a production record
     */
    public function syncOutputs(ProductionRecord $record, array $outputsData): array
    {
        return DB::transaction(function () use ($record, $outputsData) {
            $existingOutputIds = $record->outputs()->pluck('id')->toArray();
            $providedOutputIds = collect($outputsData)
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->toArray();

            // Validate ownership
            foreach ($providedOutputIds as $outputId) {
                $output = ProductionOutput::find($outputId);
                if ($output && $output->production_record_id != $record->id) {
                    throw new \Exception("El output #{$outputId} no pertenece a este proceso.");
                }
            }

            // Validate no consumptions on outputs to delete
            $outputsToDelete = array_diff($existingOutputIds, $providedOutputIds);
            foreach ($outputsToDelete as $outputId) {
                $output = ProductionOutput::find($outputId);
                if ($output && $output->consumptions()->exists()) {
                    throw new \Exception("No se puede eliminar el output #{$outputId} porque tiene consumos asociados.");
                }
            }

            $created = [];
            $updated = [];
            $deleted = [];

            // Process outputs
            foreach ($outputsData as $outputData) {
                if (isset($outputData['id']) && in_array($outputData['id'], $existingOutputIds)) {
                    $output = ProductionOutput::find($outputData['id']);
                    $output->update([
                        'product_id' => $outputData['product_id'],
                        'lot_id' => $outputData['lot_id'] ?? null,
                        'boxes' => $outputData['boxes'],
                        'weight_kg' => $outputData['weight_kg'],
                    ]);

                    if (array_key_exists('sources', $outputData)) {
                        $this->syncOutputSources($output, $outputData['sources']);
                    }

                    $output->load([
                        'productionRecord',
                        'product',
                        'sources.product',
                        'sources.productionOutputConsumption.productionOutput.product',
                    ]);
                    $updated[] = $output;
                } else {
                    $output = ProductionOutput::create([
                        'production_record_id' => $record->id,
                        'product_id' => $outputData['product_id'],
                        'lot_id' => $outputData['lot_id'] ?? null,
                        'boxes' => $outputData['boxes'],
                        'weight_kg' => $outputData['weight_kg'],
                    ]);

                    if (array_key_exists('sources', $outputData)) {
                        $this->syncOutputSources($output, $outputData['sources']);
                    } else {
                        $this->createAutomaticOutputSources($output);
                    }

                    $output->load([
                        'productionRecord',
                        'product',
                        'sources.product',
                        'sources.productionOutputConsumption.productionOutput.product',
                    ]);
                    $created[] = $output;
                }
            }

            // Delete removed outputs
            foreach ($outputsToDelete as $outputId) {
                $output = ProductionOutput::find($outputId);
                if ($output) {
                    $output->delete();
                    $deleted[] = $outputId;
                }
            }

            $record->refresh();
            $record->load([
                'production',
                'parent.process',
                'process',
                'inputs.box.product',
                'inputs.box.palletBox',
                'inputs.box.product.species',
                'inputs.box.product.captureZone',
                'outputs.product',
                'outputs.sources.product',
                'outputs.sources.productionOutputConsumption.productionOutput.product',
                'outputs.sources.productionOutputConsumption.productionOutput.productionRecord.production',
                'outputs.productionRecord.production',
            ]);

            return [
                'record' => $record,
                'summary' => [
                    'created' => count($created),
                    'updated' => count($updated),
                    'deleted' => count($deleted),
                ],
            ];
        });
    }

    /**
     * Sync consumptions for a production record
     */
    public function syncConsumptions(ProductionRecord $record, array $consumptionsData): array
    {
        if (! $record->parent_record_id) {
            throw new \Exception('El proceso no tiene un proceso padre. Solo los procesos hijos pueden consumir outputs de procesos padre.');
        }

        $parent = $record->parent;
        if (! $parent) {
            throw new \Exception('El proceso padre no existe.');
        }

        return DB::transaction(function () use ($record, $parent, $consumptionsData) {
            $existingConsumptionIds = $record->parentOutputConsumptions()->pluck('id')->toArray();
            $providedConsumptionIds = collect($consumptionsData)
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->toArray();

            // Validate ownership
            foreach ($providedConsumptionIds as $consumptionId) {
                $consumption = ProductionOutputConsumption::find($consumptionId);
                if ($consumption && $consumption->production_record_id != $record->id) {
                    throw new \Exception("El consumo #{$consumptionId} no pertenece a este proceso.");
                }
            }

            // Validate availability
            $outputsToValidate = [];
            foreach ($consumptionsData as $consumptionData) {
                $outputId = $consumptionData['production_output_id'];
                if (! isset($outputsToValidate[$outputId])) {
                    $outputsToValidate[$outputId] = [
                        'output' => ProductionOutput::findOrFail($outputId),
                        'total_requested_weight' => 0,
                        'total_requested_boxes' => 0,
                    ];
                }
                $outputsToValidate[$outputId]['total_requested_weight'] += $consumptionData['consumed_weight_kg'];
                $outputsToValidate[$outputId]['total_requested_boxes'] += ($consumptionData['consumed_boxes'] ?? 0);
            }

            // Validate each output
            foreach ($outputsToValidate as $outputId => $validationData) {
                $output = $validationData['output'];

                if ($output->production_record_id !== $parent->id) {
                    throw new \Exception("El output #{$outputId} no pertenece al proceso padre directo.");
                }

                $currentConsumedWeight = ProductionOutputConsumption::where('production_output_id', $outputId)
                    ->where('production_record_id', '!=', $record->id)
                    ->sum('consumed_weight_kg');

                $currentConsumedBoxes = ProductionOutputConsumption::where('production_output_id', $outputId)
                    ->where('production_record_id', '!=', $record->id)
                    ->sum('consumed_boxes');

                $availableWeight = $output->weight_kg - $currentConsumedWeight;
                $availableBoxes = $output->boxes - $currentConsumedBoxes;

                if ($validationData['total_requested_weight'] > $availableWeight) {
                    throw new \Exception("No hay suficiente peso disponible en el output #{$outputId}. Disponible: {$availableWeight}kg, solicitado: {$validationData['total_requested_weight']}kg");
                }

                if ($validationData['total_requested_boxes'] > $availableBoxes) {
                    throw new \Exception("No hay suficientes cajas disponibles en el output #{$outputId}. Disponible: {$availableBoxes}, solicitado: {$validationData['total_requested_boxes']}");
                }
            }

            $created = [];
            $updated = [];
            $deleted = [];

            // Process consumptions
            foreach ($consumptionsData as $consumptionData) {
                if (isset($consumptionData['id']) && in_array($consumptionData['id'], $existingConsumptionIds)) {
                    $consumption = ProductionOutputConsumption::find($consumptionData['id']);
                    $consumption->update([
                        'production_output_id' => $consumptionData['production_output_id'],
                        'consumed_weight_kg' => $consumptionData['consumed_weight_kg'],
                        'consumed_boxes' => $consumptionData['consumed_boxes'] ?? 0,
                        'notes' => $consumptionData['notes'] ?? null,
                    ]);
                    $consumption->load(['productionRecord.process', 'productionOutput.product']);
                    $updated[] = $consumption;
                } else {
                    $existing = ProductionOutputConsumption::where('production_record_id', $record->id)
                        ->where('production_output_id', $consumptionData['production_output_id'])
                        ->first();

                    if ($existing) {
                        throw new \Exception("Ya existe un consumo para el output #{$consumptionData['production_output_id']}. Use el ID del consumo existente para actualizarlo.");
                    }

                    $consumption = ProductionOutputConsumption::create([
                        'production_record_id' => $record->id,
                        'production_output_id' => $consumptionData['production_output_id'],
                        'consumed_weight_kg' => $consumptionData['consumed_weight_kg'],
                        'consumed_boxes' => $consumptionData['consumed_boxes'] ?? 0,
                        'notes' => $consumptionData['notes'] ?? null,
                    ]);
                    $consumption->load(['productionRecord.process', 'productionOutput.product']);
                    $created[] = $consumption;
                }
            }

            // Delete removed consumptions
            $consumptionsToDelete = array_diff($existingConsumptionIds, $providedConsumptionIds);
            foreach ($consumptionsToDelete as $consumptionId) {
                $consumption = ProductionOutputConsumption::find($consumptionId);
                if ($consumption) {
                    $consumption->delete();
                    $deleted[] = $consumptionId;
                }
            }

            $record->refresh();
            $record->load([
                'production',
                'parent.process',
                'process',
                'inputs.box.product',
                'inputs.box.palletBox',
                'outputs.product',
                'outputs.sources.product',
                'outputs.sources.productionOutputConsumption.productionOutput.product',
                'outputs.sources.productionOutputConsumption.productionOutput.productionRecord.production',
                'outputs.productionRecord.production',
                'parentOutputConsumptions.productionOutput.product',
                'parentOutputConsumptions.productionOutput.productionRecord.process',
                'parentOutputConsumptions.productionOutput.productionRecord.production',
            ]);

            return [
                'record' => $record,
                'summary' => [
                    'created' => count($created),
                    'updated' => count($updated),
                    'deleted' => count($deleted),
                ],
            ];
        });
    }

    /**
     * Get sources data for a production record (stock products, parent outputs, totals).
     * Used by getSourcesData endpoint.
     *
     * @return array{productionRecord: array, stockProducts: \Illuminate\Support\Collection, parentOutputs: \Illuminate\Support\Collection, totals: array}
     */
    public function getSourcesData(ProductionRecord $record): array
    {
        $record->load([
            'production',
            'process',
            'inputs.box.product',
            'inputs.box.palletBox.pallet.reception',
            'parentOutputConsumptions.productionOutput.product',
            'parentOutputConsumptions.productionOutput.productionRecord.process',
        ]);

        $stockProducts = $record->inputs
            ->filter(fn ($input) => $input->box && $input->box->article_id)
            ->groupBy(fn ($input) => (int) $input->box->article_id)
            ->map(function ($inputs) {
                $firstBox = $inputs->first()?->box;
                $totalWeight = $inputs->sum(fn ($input) => (float) ($input->box->net_weight ?? 0));
                $totalCost = $inputs->sum(fn ($input) => (float) ($input->box->total_cost ?? 0));

                return [
                    'productId' => $firstBox?->product?->id,
                    'product' => [
                        'id' => $firstBox?->product?->id,
                        'name' => $firstBox?->product?->name,
                    ],
                    'inputCount' => $inputs->count(),
                    'totalWeight' => $totalWeight,
                    'totalCost' => $totalCost,
                    'costPerKg' => $totalWeight > 0 ? $totalCost / $totalWeight : null,
                ];
            })
            ->values();

        $parentOutputs = $record->parentOutputConsumptions->map(function ($consumption) {
            $output = $consumption->productionOutput;
            if (! $output) {
                return null;
            }
            $parentRecord = $output->productionRecord;
            $processName = $parentRecord && $parentRecord->process
                ? $parentRecord->process->name
                : 'Proceso padre';

            return [
                'productionOutputConsumptionId' => $consumption->id,
                'productionOutputId' => $output->id,
                'product' => [
                    'id' => $output->product->id ?? null,
                    'name' => $output->product->name ?? null,
                ],
                'lotId' => $output->lot_id,
                'consumedWeightKg' => (float) ($consumption->consumed_weight_kg ?? 0),
                'consumedBoxes' => (int) ($consumption->consumed_boxes ?? 0),
                'outputTotalWeight' => (float) ($output->weight_kg ?? 0),
                'outputTotalBoxes' => (int) ($output->boxes ?? 0),
                'outputAvailableWeight' => (float) ($output->available_weight_kg ?? 0),
                'outputAvailableBoxes' => (int) ($output->available_boxes ?? 0),
                'costPerKg' => $output->cost_per_kg !== null ? (float) $output->cost_per_kg : null,
                'totalCost' => $output->total_cost !== null ? (float) $output->total_cost : null,
                'parentProcess' => [
                    'id' => $parentRecord->id ?? null,
                    'name' => $processName,
                    'processId' => $parentRecord->process_id ?? null,
                ],
            ];
        })->filter()->values();

        $totalStockWeight = $stockProducts->sum('totalWeight');
        $totalStockCost = $stockProducts->sum(fn ($product) => $product['totalCost'] ?? 0);
        $totalParentWeight = $parentOutputs->sum('consumedWeightKg');
        $totalParentCost = $parentOutputs->sum(fn ($output) => ($output['costPerKg'] ?? 0) * ($output['consumedWeightKg'] ?? 0));
        $totalInputWeight = $totalStockWeight + $totalParentWeight;
        $totalInputCost = $totalStockCost + $totalParentCost;

        return [
            'productionRecord' => [
                'id' => $record->id,
                'processId' => $record->process_id,
                'processName' => $record->process ? $record->process->name : null,
                'productionId' => $record->production_id,
                'productionLot' => $record->production ? $record->production->lot : null,
                'totalInputWeight' => $totalInputWeight,
                'totalInputCost' => $totalInputCost,
            ],
            'stockProducts' => $stockProducts,
            'parentOutputs' => $parentOutputs,
            'totals' => [
                'stock' => [
                    'count' => $stockProducts->count(),
                    'totalWeight' => $totalStockWeight,
                    'totalCost' => $totalStockCost,
                    'averageCostPerKg' => $totalStockWeight > 0 ? $totalStockCost / $totalStockWeight : null,
                ],
                'parent' => [
                    'count' => $parentOutputs->count(),
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
     * Sync the sources of an output from payload data.
     */
    protected function syncOutputSources(ProductionOutput $output, ?array $sources): void
    {
        $output->sources()->delete();

        if (! is_array($sources) || count($sources) === 0) {
            return;
        }

        foreach ($sources as $sourceData) {
            ProductionOutputSource::create([
                'production_output_id' => $output->id,
                'source_type' => $sourceData['source_type'],
                'product_id' => $sourceData['product_id'] ?? null,
                'production_output_consumption_id' => $sourceData['production_output_consumption_id'] ?? null,
                'contributed_weight_kg' => $sourceData['contributed_weight_kg'] ?? null,
                'contribution_percentage' => $sourceData['contribution_percentage'] ?? null,
                'contributed_boxes' => $sourceData['contributed_boxes'] ?? 0,
            ]);
        }
    }

    /**
     * Create automatic sources using the record's real consumption.
     */
    protected function createAutomaticOutputSources(ProductionOutput $output): void
    {
        $record = $output->productionRecord;
        if (! $record) {
            return;
        }

        $inputs = $record->inputs()->with('box.product')->get();
        $consumptions = $record->parentOutputConsumptions;
        $totalInputWeight = $record->total_input_weight;

        if ($totalInputWeight <= 0) {
            return;
        }

        $inputsByProduct = $inputs
            ->filter(fn ($input) => $input->box && $input->box->article_id)
            ->groupBy(fn ($input) => (int) $input->box->article_id);

        foreach ($inputsByProduct as $productId => $productInputs) {
            $inputWeight = $productInputs->sum(fn ($input) => (float) ($input->box->net_weight ?? 0));
            if ($inputWeight <= 0) {
                continue;
            }

            ProductionOutputSource::create([
                'production_output_id' => $output->id,
                'source_type' => ProductionOutputSource::SOURCE_TYPE_STOCK_PRODUCT,
                'product_id' => (int) $productId,
                'production_output_consumption_id' => null,
                'contributed_weight_kg' => $inputWeight,
                'contribution_percentage' => ($inputWeight / $totalInputWeight) * 100,
                'contributed_boxes' => 0,
            ]);
        }

        foreach ($consumptions as $consumption) {
            $consumptionWeight = $consumption->consumed_weight_kg ?? 0;
            if ($consumptionWeight <= 0) {
                continue;
            }

            ProductionOutputSource::create([
                'production_output_id' => $output->id,
                'source_type' => ProductionOutputSource::SOURCE_TYPE_PARENT_OUTPUT,
                'product_id' => null,
                'production_output_consumption_id' => $consumption->id,
                'contributed_weight_kg' => $consumptionWeight,
                'contribution_percentage' => ($consumptionWeight / $totalInputWeight) * 100,
                'contributed_boxes' => $consumption->consumed_boxes ?? 0,
            ]);
        }
    }

    /**
     * Get options for parent records
     */
    public function getOptions(array $filters = []): Collection
    {
        $query = ProductionRecord::query();
        $query->with(['production', 'process']);

        if (isset($filters['production_id'])) {
            $query->where('production_id', $filters['production_id']);
        }

        if (isset($filters['exclude_id'])) {
            $excludeId = $filters['exclude_id'];
            $descendantIds = $this->getDescendantIds($excludeId);
            $excludeIds = array_merge([$excludeId], $descendantIds);
            $query->whereNotIn('id', $excludeIds);
        }

        $query->orderBy('started_at', 'desc')
            ->orderBy('id', 'desc');

        return $query->get();
    }

    /**
     * Get descendant IDs recursively
     */
    private function getDescendantIds($parentId): array
    {
        $descendantIds = [];
        $children = ProductionRecord::where('parent_record_id', $parentId)->pluck('id');

        foreach ($children as $childId) {
            $descendantIds[] = $childId;
            $descendantIds = array_merge($descendantIds, $this->getDescendantIds($childId));
        }

        return $descendantIds;
    }
}
