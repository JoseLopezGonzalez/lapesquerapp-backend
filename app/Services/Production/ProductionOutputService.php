<?php

namespace App\Services\Production;

use App\Models\ProductionOutput;
use Illuminate\Support\Facades\DB;

class ProductionOutputService
{
    /**
     * Create a production output
     */
    public function create(array $data): ProductionOutput
    {
        $output = ProductionOutput::create($data);
        $output->load(['productionRecord', 'product']);

        return $output;
    }

    /**
     * Update a production output
     */
    public function update(ProductionOutput $output, array $data): ProductionOutput
    {
        $output->update($data);
        $output->load(['productionRecord', 'product']);

        return $output;
    }

    /**
     * Delete a production output
     */
    public function delete(ProductionOutput $output): bool
    {
        return $output->delete();
    }

    /**
     * Create multiple production outputs
     */
    public function createMultiple(int $productionRecordId, array $outputsData): array
    {
        return DB::transaction(function () use ($productionRecordId, $outputsData) {
            $created = [];
            $errors = [];

            foreach ($outputsData as $index => $outputData) {
                try {
                    $output = ProductionOutput::create([
                        'production_record_id' => $productionRecordId,
                        'product_id' => $outputData['product_id'],
                        'lot_id' => $outputData['lot_id'] ?? null,
                        'boxes' => $outputData['boxes'],
                        'weight_kg' => $outputData['weight_kg'],
                    ]);

                    $output->load(['productionRecord', 'product']);
                    $created[] = $output;
                } catch (\Exception $e) {
                    $errors[] = "Error en la salida #{$index}: " . $e->getMessage();
                }
            }

            return [
                'created' => $created,
                'errors' => $errors,
            ];
        });
    }
}

