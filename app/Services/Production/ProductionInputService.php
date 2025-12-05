<?php

namespace App\Services\Production;

use App\Models\ProductionInput;
use Illuminate\Support\Facades\DB;

class ProductionInputService
{
    /**
     * Create a production input
     */
    public function create(array $data): ProductionInput
    {
        // Check if box is already assigned
        $existing = ProductionInput::where('production_record_id', $data['production_record_id'])
            ->where('box_id', $data['box_id'])
            ->first();

        if ($existing) {
            throw new \Exception('La caja ya está asignada a este proceso.');
        }

        $input = ProductionInput::create($data);
        $input->load(['productionRecord', 'box.product']);

        return $input;
    }

    /**
     * Create multiple production inputs
     */
    public function createMultiple(int $productionRecordId, array $boxIds): array
    {
        return DB::transaction(function () use ($productionRecordId, $boxIds) {
            $created = [];
            $errors = [];

            foreach ($boxIds as $boxId) {
                try {
                    $existing = ProductionInput::where('production_record_id', $productionRecordId)
                        ->where('box_id', $boxId)
                        ->first();

                    if ($existing) {
                        $errors[] = "La caja {$boxId} ya está asignada a este proceso.";
                        continue;
                    }

                    $input = ProductionInput::create([
                        'production_record_id' => $productionRecordId,
                        'box_id' => $boxId,
                    ]);

                    $input->load(['productionRecord', 'box.product']);
                    $created[] = $input;
                } catch (\Exception $e) {
                    $errors[] = "Error al crear entrada para caja {$boxId}: " . $e->getMessage();
                }
            }

            return [
                'created' => $created,
                'errors' => $errors,
            ];
        });
    }

    /**
     * Delete a production input
     */
    public function delete(ProductionInput $input): bool
    {
        return $input->delete();
    }
}

