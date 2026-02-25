<?php

namespace App\Services\v2;

use App\Models\Order;
use App\Models\Pallet;
use App\Models\Store;
use App\Models\StoredPallet;
use App\Services\v2\PalletTimelineService;

class PalletActionService
{
    public static function assignToPosition(int $positionId, array $palletIds): void
    {
        foreach ($palletIds as $palletId) {
            $pallet = Pallet::with('storedPallet.store')->find($palletId);
            $stored = StoredPallet::firstOrNew(['pallet_id' => $palletId]);
            $stored->position = $positionId;
            $stored->save();
            $stored->load('store');
            if ($pallet) {
                $store = $stored->store;
                $storeName = $store?->name ?? null;
                $action = $storeName
                    ? "Posición asignada: {$positionId} ({$storeName})"
                    : "Posición asignada: {$positionId}";
                PalletTimelineService::record($pallet, 'position_assigned', $action, [
                    'positionId' => $positionId,
                    'positionName' => (string) $positionId,
                    'storeId' => $store?->id,
                    'storeName' => $storeName,
                ]);
            }
        }
    }

    public static function moveToStore(int $palletId, int $storeId): array
    {
        $pallet = Pallet::with('storedPallet.store')->findOrFail($palletId);
        $previousStore = $pallet->storedPallet?->store;
        $previousStoreId = $previousStore?->id;
        $previousStoreName = $previousStore?->name;

        if ($pallet->status === Pallet::STATE_REGISTERED) {
            $pallet->status = Pallet::STATE_STORED;
            $pallet->save();
            PalletTimelineService::record($pallet, 'state_changed', 'Estado cambiado de Registrado a Almacenado', [
                'fromId' => Pallet::STATE_REGISTERED,
                'from' => 'registered',
                'toId' => Pallet::STATE_STORED,
                'to' => 'stored',
            ]);
        } elseif ($pallet->status !== Pallet::STATE_STORED) {
            return ['error' => 'El palet no está en estado almacenado o registrado'];
        }

        $store = Store::find($storeId);
        $storeName = $store?->name ?? null;

        $storedPallet = StoredPallet::firstOrNew(['pallet_id' => $palletId]);
        $storedPallet->store_id = $storeId;
        $storedPallet->position = null;
        $storedPallet->save();

        PalletTimelineService::record($pallet, 'store_assigned', $storeName ? "Movido al almacén {$storeName}" : "Movido al almacén #{$storeId}", [
            'storeId' => $storeId,
            'storeName' => $storeName,
            'previousStoreId' => $previousStoreId,
            'previousStoreName' => $previousStoreName,
        ]);

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
        $stored = StoredPallet::with('store')->where('pallet_id', $palletId)->first();

        if (! $stored) {
            return null;
        }

        $pallet = Pallet::find($palletId);
        $previousPosition = $stored->position;
        $previousStoreName = $stored->store?->name;

        $stored->position = null;
        $stored->save();

        if ($pallet) {
            $action = $previousStoreName
                ? "Posición {$previousPosition} eliminada ({$previousStoreName})"
                : "Posición {$previousPosition} eliminada";
            PalletTimelineService::record($pallet, 'position_unassigned', $action, [
                'previousPositionId' => $previousPosition,
                'previousPositionName' => (string) $previousPosition,
            ]);
        }

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

        $stateName = Pallet::getStateName($stateId);
        foreach ($pallets as $pallet) {
            if ($pallet->status != $stateId) {
                $fromId = $pallet->status;
                $fromName = Pallet::getStateName($fromId);
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
                PalletTimelineService::record($pallet, 'state_changed', sprintf('Estado cambiado de %s a %s', ucfirst($fromName), ucfirst($stateName)), [
                    'fromId' => $fromId,
                    'from' => $fromName,
                    'toId' => $stateId,
                    'to' => $stateName,
                ]);
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

        $order = Order::find($orderId);
        $orderRef = $order && $order->reference ? $order->reference : '#' . $orderId;
        $pallet->order_id = $orderId;
        $pallet->save();
        PalletTimelineService::record($pallet, 'order_linked', "Vinculado al pedido {$orderRef}", [
            'orderId' => $orderId,
            'orderReference' => $orderRef,
        ]);
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
        $order = Order::find($orderId);
        $orderRef = $order && $order->reference ? $order->reference : '#' . $orderId;
        $pallet->order_id = null;
        if ($pallet->status !== Pallet::STATE_REGISTERED) {
            $pallet->status = Pallet::STATE_REGISTERED;
        }
        $pallet->unStore();
        $pallet->save();
        PalletTimelineService::record($pallet, 'order_unlinked', "Desvinculado del pedido {$orderRef}", [
            'orderId' => $orderId,
            'orderReference' => $orderRef,
        ]);
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
