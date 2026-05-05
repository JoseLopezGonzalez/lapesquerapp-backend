<?php

namespace App\Services\Production;

use App\Models\Box;
use App\Models\ProductionInput;
use Illuminate\Support\Facades\DB;

class ProductionInputService
{
    public function __construct(
        private ProductionLotLockService $lotLock,
    ) {}

    /**
     * Create a production input
     */
    public function create(array $data): ProductionInput
    {
        $box = Box::findOrFail($data['box_id']);
        $this->lotLock->assertBoxIsMutable($box, 'crear input de producción');
        // Check if box is already assigned
        $existing = ProductionInput::where('production_record_id', $data['production_record_id'])
            ->where('box_id', $data['box_id'])
            ->first();

        if ($existing) {
            throw new \Exception('La caja ya está asignada a este proceso.');
        }

        $input = ProductionInput::create($data);
        $input->load(['productionRecord', 'box.product', 'box.product.species', 'box.product.captureZone', 'box.palletBox.pallet']);

        // Actualizar estado del palet si existe
        // Acceder al palet a través de palletBox para evitar problemas con accessors
        $pallet = $input->box->palletBox->pallet ?? null;
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

                    $input->load(['productionRecord', 'box.product', 'box.product.species', 'box.product.captureZone', 'box.palletBox.pallet']);
                    $created[] = $input;
                    
                    // Track pallet for state update (avoid duplicates)
                    // Acceder al palet a través de palletBox para evitar problemas con accessors
                    $pallet = $input->box->palletBox->pallet ?? null;
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
     * Sincroniza inputs de un proceso de producción aplicando diff.
     *
     * @return array{
     *   added: array<int, ProductionInput>,
     *   removed: array<int, int>,
     *   unchanged: array<int, int>
     * }
     */
    public function syncMultiple(int $productionRecordId, array $boxIds): array
    {
        return DB::transaction(function () use ($productionRecordId, $boxIds) {
            $desiredBoxIds = collect($boxIds)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $currentInputs = ProductionInput::with(['box.palletBox.pallet'])
                ->where('production_record_id', $productionRecordId)
                ->get()
                ->keyBy('box_id');

            $currentBoxIds = $currentInputs->keys()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $toAdd = array_values(array_diff($desiredBoxIds, $currentBoxIds));
            $toRemove = array_values(array_diff($currentBoxIds, $desiredBoxIds));
            $unchanged = array_values(array_intersect($currentBoxIds, $desiredBoxIds));

            $palletsToUpdate = [];

            if ($toRemove !== []) {
                foreach ($toRemove as $boxId) {
                    /** @var ProductionInput $input */
                    $input = $currentInputs->get($boxId);
                    if (! $input || ! $input->box) {
                        continue;
                    }

                    // Mantener restricciones actuales: si el lote está cerrado, falla.
                    $this->lotLock->assertBoxIsMutable($input->box, 'eliminar input de producción');

                    $pallet = $input->box->palletBox->pallet ?? null;
                    if ($pallet) {
                        $palletsToUpdate[$pallet->id] = $pallet->id;
                    }

                    $input->delete();
                }
            }

            $added = [];
            if ($toAdd !== []) {
                foreach ($toAdd as $boxId) {
                    $input = ProductionInput::create([
                        'production_record_id' => $productionRecordId,
                        'box_id' => $boxId,
                    ]);

                    $input->load(['productionRecord', 'box.product', 'box.product.species', 'box.product.captureZone', 'box.palletBox.pallet']);

                    $pallet = $input->box->palletBox->pallet ?? null;
                    if ($pallet) {
                        $palletsToUpdate[$pallet->id] = $pallet->id;
                    }

                    $added[] = $input;
                }
            }

            foreach ($palletsToUpdate as $palletId) {
                $pallet = \App\Models\Pallet::find($palletId);
                if ($pallet) {
                    $pallet->updateStateBasedOnBoxes();
                }
            }

            return [
                'added' => $added,
                'removed' => $toRemove,
                'unchanged' => $unchanged,
            ];
        });
    }

    /**
     * Delete a production input
     */
    public function delete(ProductionInput $input): bool
    {
        $input->load(['box.palletBox.pallet']);
        $this->lotLock->assertBoxIsMutable($input->box, 'eliminar input de producción');
        
        // Obtener el palet antes de eliminar
        // Acceder al palet a través de palletBox para evitar problemas con accessors
        $pallet = $input->box->palletBox->pallet ?? null;
        
        $deleted = $input->delete();
        
        // Si se eliminó correctamente y hay un palet, actualizar su estado
        if ($deleted && $pallet) {
            $pallet->updateStateBasedOnBoxes();
        }
        
        return $deleted;
    }

    /**
     * Delete multiple production inputs
     */
    public function deleteMultiple(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $inputs = ProductionInput::with(['box.palletBox.pallet'])
                ->whereIn('id', $ids)
                ->get();

            foreach ($inputs as $input) {
                $this->lotLock->assertBoxIsMutable($input->box, 'eliminar input de producción');
            }

            // Rastrear palets que necesitan actualización (evitar duplicados)
            $palletsToUpdate = [];

            foreach ($inputs as $input) {
                // Obtener el palet antes de eliminar
                // Acceder al palet a través de palletBox para evitar problemas con accessors
                $pallet = $input->box->palletBox->pallet ?? null;
                
                if ($pallet && !in_array($pallet->id, $palletsToUpdate)) {
                    $palletsToUpdate[] = $pallet->id;
                }
            }

            // Eliminar los inputs
            $deletedCount = ProductionInput::whereIn('id', $ids)->delete();

            // Actualizar cada palet una sola vez después de todas las eliminaciones
            foreach ($palletsToUpdate as $palletId) {
                $pallet = \App\Models\Pallet::find($palletId);
                if ($pallet) {
                    $pallet->updateStateBasedOnBoxes();
                }
            }

            return $deletedCount;
        });
    }
}

