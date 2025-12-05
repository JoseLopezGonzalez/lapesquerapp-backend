<?php

namespace App\Services\Production;

use App\Models\ProductionOutputConsumption;
use App\Models\ProductionRecord;
use App\Models\ProductionOutput;
use Illuminate\Support\Facades\DB;

class ProductionOutputConsumptionService
{
    /**
     * Create a production output consumption
     */
    public function create(array $data): ProductionOutputConsumption
    {
        $record = ProductionRecord::findOrFail($data['production_record_id']);
        $output = ProductionOutput::findOrFail($data['production_output_id']);

        // Validate parent exists
        if (!$record->parent_record_id) {
            throw new \Exception('El proceso no tiene un proceso padre. Solo los procesos hijos pueden consumir outputs de procesos padre.');
        }

        // Validate output belongs to direct parent
        if ($record->parent_record_id !== $output->production_record_id) {
            throw new \Exception('El output debe pertenecer al proceso padre directo del proceso que consume.');
        }

        // Check for existing consumption
        $existing = ProductionOutputConsumption::where('production_record_id', $data['production_record_id'])
            ->where('production_output_id', $data['production_output_id'])
            ->first();

        if ($existing) {
            throw new \Exception('Ya existe un consumo de este output para este proceso. Use el método update para modificarlo.');
        }

        // Validate availability
        $totalConsumed = ProductionOutputConsumption::where('production_output_id', $data['production_output_id'])
            ->sum('consumed_weight_kg');

        $availableWeight = $output->weight_kg - $totalConsumed;

        if ($data['consumed_weight_kg'] > $availableWeight) {
            throw new \Exception("No hay suficiente peso disponible en el output. Disponible: {$availableWeight}kg, solicitado: {$data['consumed_weight_kg']}kg");
        }

        if (isset($data['consumed_boxes']) && $data['consumed_boxes'] > 0) {
            $totalConsumedBoxes = ProductionOutputConsumption::where('production_output_id', $data['production_output_id'])
                ->sum('consumed_boxes');

            $availableBoxes = $output->boxes - $totalConsumedBoxes;

            if ($data['consumed_boxes'] > $availableBoxes) {
                throw new \Exception("No hay suficientes cajas disponibles en el output. Disponible: {$availableBoxes}, solicitado: {$data['consumed_boxes']}");
            }
        }

        if (!isset($data['consumed_boxes'])) {
            $data['consumed_boxes'] = 0;
        }

        $consumption = ProductionOutputConsumption::create($data);
        $consumption->load(['productionRecord.process', 'productionOutput.product']);

        return $consumption;
    }

    /**
     * Update a production output consumption
     */
    public function update(ProductionOutputConsumption $consumption, array $data): ProductionOutputConsumption
    {
        $output = $consumption->productionOutput;

        // Validate weight availability if updating
        if (isset($data['consumed_weight_kg'])) {
            $totalConsumed = ProductionOutputConsumption::where('production_output_id', $consumption->production_output_id)
                ->where('id', '!=', $consumption->id)
                ->sum('consumed_weight_kg');

            $availableWeight = $output->weight_kg - $totalConsumed;

            if ($data['consumed_weight_kg'] > $availableWeight) {
                throw new \Exception("No hay suficiente peso disponible. Disponible: {$availableWeight}kg, solicitado: {$data['consumed_weight_kg']}kg");
            }
        }

        // Validate boxes availability if updating
        if (isset($data['consumed_boxes']) && $data['consumed_boxes'] > 0) {
            $totalConsumedBoxes = ProductionOutputConsumption::where('production_output_id', $consumption->production_output_id)
                ->where('id', '!=', $consumption->id)
                ->sum('consumed_boxes');

            $availableBoxes = $output->boxes - $totalConsumedBoxes;

            if ($data['consumed_boxes'] > $availableBoxes) {
                throw new \Exception("No hay suficientes cajas disponibles. Disponible: {$availableBoxes}, solicitado: {$data['consumed_boxes']}");
            }
        }

        $consumption->update($data);
        $consumption->load(['productionRecord.process', 'productionOutput.product']);

        return $consumption;
    }

    /**
     * Delete a production output consumption
     */
    public function delete(ProductionOutputConsumption $consumption): bool
    {
        return $consumption->delete();
    }

    /**
     * Create multiple production output consumptions
     */
    public function createMultiple(int $productionRecordId, array $consumptionsData): array
    {
        $record = ProductionRecord::findOrFail($productionRecordId);

        if (!$record->parent_record_id) {
            throw new \Exception('El proceso no tiene un proceso padre. Solo los procesos hijos pueden consumir outputs de procesos padre.');
        }

        $parent = $record->parent;
        if (!$parent) {
            throw new \Exception('El proceso padre no existe.');
        }

        return DB::transaction(function () use ($record, $parent, $productionRecordId, $consumptionsData) {
            // Validate availability first
            $outputsToValidate = [];
            foreach ($consumptionsData as $consumptionData) {
                $outputId = $consumptionData['production_output_id'];
                if (!isset($outputsToValidate[$outputId])) {
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
                    ->sum('consumed_weight_kg');

                $currentConsumedBoxes = ProductionOutputConsumption::where('production_output_id', $outputId)
                    ->sum('consumed_boxes');

                $availableWeight = $output->weight_kg - $currentConsumedWeight;
                $availableBoxes = $output->boxes - $currentConsumedBoxes;

                if ($validationData['total_requested_weight'] > $availableWeight) {
                    throw new \Exception("Output #{$outputId}: No hay suficiente peso disponible. Disponible: {$availableWeight}kg, solicitado: {$validationData['total_requested_weight']}kg");
                }

                if ($validationData['total_requested_boxes'] > $availableBoxes) {
                    throw new \Exception("Output #{$outputId}: No hay suficientes cajas disponibles. Disponible: {$availableBoxes}, solicitado: {$validationData['total_requested_boxes']}");
                }
            }

            $created = [];
            $errors = [];

            // Create consumptions
            foreach ($consumptionsData as $index => $consumptionData) {
                try {
                    $existing = ProductionOutputConsumption::where('production_record_id', $productionRecordId)
                        ->where('production_output_id', $consumptionData['production_output_id'])
                        ->first();

                    if ($existing) {
                        $errors[] = "Consumo #{$index}: Ya existe un consumo para el output #{$consumptionData['production_output_id']}.";
                        continue;
                    }

                    $consumption = ProductionOutputConsumption::create([
                        'production_record_id' => $productionRecordId,
                        'production_output_id' => $consumptionData['production_output_id'],
                        'consumed_weight_kg' => $consumptionData['consumed_weight_kg'],
                        'consumed_boxes' => $consumptionData['consumed_boxes'] ?? 0,
                        'notes' => $consumptionData['notes'] ?? null,
                    ]);

                    $consumption->load(['productionRecord.process', 'productionOutput.product']);
                    $created[] = $consumption;
                } catch (\Exception $e) {
                    $errors[] = "Error en el consumo #{$index}: " . $e->getMessage();
                }
            }

            if (empty($created) && !empty($errors)) {
                throw new \Exception('No se pudo crear ningún consumo. ' . implode(' ', $errors));
            }

            return [
                'created' => $created,
                'errors' => $errors,
            ];
        });
    }

    /**
     * Get available outputs for a production record
     */
    public function getAvailableOutputs(int $productionRecordId): array
    {
        $record = ProductionRecord::findOrFail($productionRecordId);

        if (!$record->parent_record_id) {
            return [];
        }

        $parent = $record->parent;
        if (!$parent) {
            return [];
        }

        $outputs = $parent->outputs()->with('product')->get();

        return $outputs->map(function ($output) use ($productionRecordId) {
            $consumedWeight = ProductionOutputConsumption::where('production_output_id', $output->id)
                ->sum('consumed_weight_kg');

            $consumedBoxes = ProductionOutputConsumption::where('production_output_id', $output->id)
                ->sum('consumed_boxes');

            $availableWeight = max(0, $output->weight_kg - $consumedWeight);
            $availableBoxes = max(0, $output->boxes - $consumedBoxes);

            return [
                'output' => $output,
                'totalWeight' => $output->weight_kg,
                'totalBoxes' => $output->boxes,
                'consumedWeight' => $consumedWeight,
                'consumedBoxes' => $consumedBoxes,
                'availableWeight' => $availableWeight,
                'availableBoxes' => $availableBoxes,
                'hasExistingConsumption' => ProductionOutputConsumption::where('production_record_id', $productionRecordId)
                    ->where('production_output_id', $output->id)
                    ->exists(),
            ];
        })->filter(function ($item) {
            return $item['availableWeight'] > 0 || $item['availableBoxes'] > 0;
        })->values()->toArray();
    }
}

