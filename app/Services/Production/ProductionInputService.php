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
        $input->load(['productionRecord', 'box.product', 'box.pallet']);

        // Actualizar estado del palet si existe
        $pallet = $input->box->pallet ?? null;
        if ($pallet) {
            $pallet->updateStateBasedOnBoxes();
        }

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
            $palletsToUpdate = []; // Track pallets that need state update

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

                    $input->load(['productionRecord', 'box.product', 'box.pallet']);
                    $created[] = $input;
                    
                    // Track pallet for state update (avoid duplicates)
                    $pallet = $input->box->pallet ?? null;
                    if ($pallet && !in_array($pallet->id, $palletsToUpdate)) {
                        $palletsToUpdate[] = $pallet->id;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error al crear entrada para caja {$boxId}: " . $e->getMessage();
                }
            }

            // Update each pallet state only once after all inputs are created
            foreach ($palletsToUpdate as $palletId) {
                $pallet = \App\Models\Pallet::find($palletId);
                if ($pallet) {
                    $pallet->updateStateBasedOnBoxes();
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
        // Obtener el palet antes de eliminar
        $box = $input->box;
        $pallet = $box->pallet ?? null;
        
        $deleted = $input->delete();
        
        // Si se eliminó correctamente y hay un palet, actualizar su estado
        if ($deleted && $pallet) {
            $pallet->updateStateBasedOnBoxes();
        }
        
        return $deleted;
    }
}

