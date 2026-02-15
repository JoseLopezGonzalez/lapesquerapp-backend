<?php

namespace App\Services\v2;

use App\Models\Pallet;
use App\Models\StoredPallet;

class PalletActionService
{
    public static function assignToPosition(int $positionId, array $palletIds): void
    {
        foreach ($palletIds as $palletId) {
            $stored = StoredPallet::firstOrNew(['pallet_id' => $palletId]);
            $stored->position = $positionId;
            $stored->save();
        }
    }

    public static function moveToStore(int $palletId, int $storeId): array
    {
        $pallet = Pallet::findOrFail($palletId);

        if ($pallet->status === Pallet::STATE_REGISTERED) {
            $pallet->status = Pallet::STATE_STORED;
            $pallet->save();
        } elseif ($pallet->status !== Pallet::STATE_STORED) {
            return ['error' => 'El palet no está en estado almacenado o registrado'];
        }

        $storedPallet = StoredPallet::firstOrNew(['pallet_id' => $palletId]);
        $storedPallet->store_id = $storeId;
        $storedPallet->position = null;
        $storedPallet->save();

        $pallet->refresh();
        $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $pallet->id))->first();

        return ['pallet' => $pallet];
    }

    public static function moveMultipleToStore(array $palletIds, int $storeId): array
    {
        $movedCount = 0;
        $errors = [];

        foreach ($palletIds as $palletId) {
            try {
                $result = self::moveToStore($palletId, $storeId);
                if (isset($result['error'])) {
                    $errors[] = ['pallet_id' => $palletId, 'error' => $result['error']];
                } else {
                    $movedCount++;
                }
            } catch (\Exception $e) {
                $errors[] = ['pallet_id' => $palletId, 'error' => $e->getMessage()];
            }
        }

        return [
            'moved_count' => $movedCount,
            'total_count' => count($palletIds),
            'errors' => $errors,
        ];
    }

    public static function unassignPosition(int $palletId): ?StoredPallet
    {
        $stored = StoredPallet::where('pallet_id', $palletId)->first();

        if (! $stored) {
            return null;
        }

        $stored->position = null;
        $stored->save();

        return $stored;
    }

    public static function bulkUpdateState(
        int $stateId,
        ?array $ids,
        ?array $filters,
        bool $applyToAll,
        ?int $defaultStoreId = null
    ): int {
        $palletsQuery = Pallet::with('storedPallet');

        if ($ids !== null && ! empty($ids)) {
            $palletsQuery->whereIn('id', $ids);
        } elseif ($filters !== null && ! empty($filters)) {
            $palletsQuery = PalletListService::applyFilters($palletsQuery, ['filters' => $filters]);
        } elseif (! $applyToAll) {
            throw new \InvalidArgumentException('No se especificó ninguna condición válida para seleccionar pallets.');
        }

        $pallets = $palletsQuery->get();
        $defaultStoreId = $defaultStoreId ?? \App\Models\Store::query()->value('id');
        $updatedCount = 0;

        foreach ($pallets as $pallet) {
            if ($pallet->status != $stateId) {
                if ($stateId !== Pallet::STATE_STORED && $pallet->storedPallet) {
                    $pallet->unStore();
                }

                if ($stateId === Pallet::STATE_STORED && ! $pallet->storedPallet && $defaultStoreId) {
                    StoredPallet::create([
                        'pallet_id' => $pallet->id,
                        'store_id' => $defaultStoreId,
                    ]);
                }

                $pallet->status = $stateId;
                $pallet->save();
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    public static function linkOrder(int $palletId, int $orderId): array
    {
        $pallet = Pallet::findOrFail($palletId);

        if ($pallet->order_id == $orderId) {
            $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $palletId))->first();

            return ['status' => 'already_linked', 'pallet' => $pallet];
        }

        if ($pallet->order_id !== null) {
            return ['error' => "El palet #{$palletId} ya está vinculado al pedido #{$pallet->order_id}. Debe desvincularlo primero."];
        }

        $pallet->order_id = $orderId;
        $pallet->save();
        $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $palletId))->first();

        return ['status' => 'linked', 'pallet' => $pallet];
    }

    public static function linkOrders(array $palletsData): array
    {
        $results = [];
        $errors = [];

        foreach ($palletsData as $data) {
            $palletId = $data['id'];
            $orderId = $data['orderId'];

            try {
                $result = self::linkOrder($palletId, $orderId);

                if (isset($result['error'])) {
                    $errors[] = ['pallet_id' => $palletId, 'order_id' => $orderId, 'error' => $result['error']];
                } elseif ($result['status'] === 'already_linked') {
                    $results[] = ['pallet_id' => $palletId, 'order_id' => $orderId, 'status' => 'already_linked', 'message' => 'El palet ya estaba vinculado a este pedido'];
                } else {
                    $results[] = ['pallet_id' => $palletId, 'order_id' => $orderId, 'status' => 'linked', 'message' => 'Palet vinculado correctamente'];
                }
            } catch (\Exception $e) {
                $errors[] = ['pallet_id' => $palletId, 'order_id' => $orderId, 'error' => $e->getMessage()];
            }
        }

        return [
            'results' => $results,
            'errors' => $errors,
        ];
    }

    public static function unlinkOrder(int $palletId): array
    {
        $pallet = Pallet::findOrFail($palletId);

        if (! $pallet->order_id) {
            $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $palletId))->first();

            return ['status' => 'already_unlinked', 'pallet' => $pallet];
        }

        $orderId = $pallet->order_id;
        $pallet->order_id = null;
        if ($pallet->status !== Pallet::STATE_REGISTERED) {
            $pallet->status = Pallet::STATE_REGISTERED;
        }
        $pallet->unStore();
        $pallet->save();

        $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $palletId))->first();

        return ['status' => 'unlinked', 'pallet' => $pallet, 'order_id' => $orderId];
    }

    public static function unlinkOrders(array $palletIds): array
    {
        $results = [];
        $errors = [];

        foreach ($palletIds as $palletId) {
            try {
                $result = self::unlinkOrder($palletId);

                if ($result['status'] === 'already_unlinked') {
                    $results[] = ['pallet_id' => $palletId, 'status' => 'already_unlinked', 'message' => 'El palet ya no está asociado a ninguna orden'];
                } else {
                    $results[] = ['pallet_id' => $palletId, 'order_id' => $result['order_id'], 'status' => 'unlinked', 'message' => 'Palet desvinculado correctamente'];
                }
            } catch (\Exception $e) {
                $errors[] = ['pallet_id' => $palletId, 'error' => $e->getMessage()];
            }
        }

        return [
            'results' => $results,
            'errors' => $errors,
        ];
    }
}
