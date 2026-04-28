<?php

namespace App\Services\Production;

use App\Models\Box;
use App\Models\Order;
use App\Models\Pallet;
use App\Models\Production;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductionClosureService
{
    /**
     * Evalúa si una producción puede cerrarse definitivamente.
     * Devuelve un array con canClose, blockingReasons y summary.
     */
    public function canClose(Production $production): array
    {
        $reasons = [];
        $lot = $production->lot;

        // 1. Debe estar abierta
        if (! $production->isOpen()) {
            if ($production->isClosed()) {
                $reasons[] = ['code' => 'already_closed', 'message' => 'La producción ya está cerrada.'];
            } else {
                $reasons[] = ['code' => 'not_open', 'message' => 'La producción no está abierta.'];
            }

            return $this->buildResult(false, $reasons, $this->buildSummary($production));
        }

        // 2. Debe tener al menos un proceso
        $records = $production->records()->with(['outputs', 'children'])->get();
        if ($records->isEmpty()) {
            $reasons[] = ['code' => 'no_processes', 'message' => 'La producción no tiene ningún proceso registrado.'];
        }

        // 3 y 4. Todos los procesos del árbol deben tener started_at y finished_at
        $allRecords = $production->records()->get();
        foreach ($allRecords as $record) {
            if ($record->started_at === null) {
                $reasons[] = [
                    'code' => 'process_not_started',
                    'message' => "El proceso ID {$record->id} no tiene fecha de inicio.",
                    'recordId' => $record->id,
                ];
            }
            if ($record->finished_at === null) {
                $reasons[] = [
                    'code' => 'process_not_finished',
                    'message' => "El proceso ID {$record->id} no tiene fecha de finalización.",
                    'recordId' => $record->id,
                ];
            }
        }

        // 5. Cada nodo final debe tener al menos un output
        $finalRecords = $production->records()
            ->whereDoesntHave('children')
            ->whereDoesntHave('outputs')
            ->whereDoesntHave('parentOutputConsumptions')
            ->get();

        foreach ($finalRecords as $record) {
            if ($record->outputs()->count() === 0) {
                $reasons[] = [
                    'code' => 'final_node_no_outputs',
                    'message' => "El proceso final ID {$record->id} no tiene ningún output registrado.",
                    'recordId' => $record->id,
                ];
            }
        }

        // 6. No debe haber pedidos pending o incident con palets de este lote
        $pendingOrders = Order::query()
            ->whereIn('status', ['pending', 'incident'])
            ->whereHas('pallets.boxes.box', function ($q) use ($lot) {
                $q->where('lot', $lot)->whereDoesntHave('productionInputs');
            })
            ->get();

        foreach ($pendingOrders as $order) {
            $reasons[] = [
                'code' => 'pending_order',
                'message' => "El pedido #{$order->id} está en estado '{$order->status}' y contiene palets de este lote.",
                'orderId' => $order->id,
            ];
        }

        // 7. Toda venta debe estar en pedido finished y palet shipped
        $unshippedSalesPallets = Pallet::query()
            ->whereNotNull('order_id')
            ->where('status', '!=', Pallet::STATE_SHIPPED)
            ->whereHas('boxes.box', function ($q) use ($lot) {
                $q->where('lot', $lot)->whereDoesntHave('productionInputs');
            })
            ->get();

        foreach ($unshippedSalesPallets as $pallet) {
            $reasons[] = [
                'code' => 'pallet_not_shipped',
                'message' => "El palet #{$pallet->id} está asignado a pedido pero no está en estado 'shipped'.",
                'palletId' => $pallet->id,
            ];
        }

        // 8 y 9. No debe quedar stock del lote (palets registered/stored sin pedido)
        $stockPallets = Pallet::query()
            ->inStock()
            ->whereNull('order_id')
            ->whereHas('boxes.box', function ($q) use ($lot) {
                $q->where('lot', $lot)->whereDoesntHave('productionInputs');
            })
            ->get();

        foreach ($stockPallets as $pallet) {
            $reasons[] = [
                'code' => 'stock_remaining',
                'message' => "El palet #{$pallet->id} tiene cajas del lote en stock.",
                'palletId' => $pallet->id,
            ];
        }

        // 10. No debe haber cajas del lote sin ningún destino (ni palet, ni reprocesadas)
        $orphanBoxes = Box::query()
            ->where('lot', $lot)
            ->whereDoesntHave('productionInputs')
            ->whereDoesntHave('palletBox')
            ->get();

        foreach ($orphanBoxes as $box) {
            $reasons[] = [
                'code' => 'orphan_box',
                'message' => "La caja #{$box->id} del lote no tiene destino (no está en palet ni fue reprocesada).",
                'boxId' => $box->id,
            ];
        }

        // 11. La conciliación debe estar en 'ok' (warning y error bloquean)
        if ($reasons === [] || $this->onlyHasNonReconciliationErrors($reasons)) {
            $reconciliation = $production->getDetailedReconciliationByProduct();
            $overallStatus = $reconciliation['summary']['overallStatus'] ?? 'error';

            if ($overallStatus !== 'ok') {
                $reasons[] = [
                    'code' => 'reconciliation_not_ok',
                    'message' => "La conciliación del lote está en estado '{$overallStatus}'. Solo se puede cerrar con estado 'ok'.",
                    'reconciliationStatus' => $overallStatus,
                ];
            }
        }

        $canClose = empty($reasons);

        return $this->buildResult($canClose, $reasons, $this->buildSummary($production));
    }

    /**
     * Cierra definitivamente una producción.
     * Ejecuta todas las validaciones dentro de una transacción con lock pesimista.
     */
    public function close(Production $production, User $user, string $reason): Production
    {
        return DB::transaction(function () use ($production, $user, $reason) {
            // Lock pesimista para evitar doble cierre concurrente
            $production = Production::lockForUpdate()->findOrFail($production->id);

            if ($production->isClosed()) {
                throw new \RuntimeException('La producción ya está cerrada.');
            }

            $check = $this->canClose($production);

            if (! $check['canClose']) {
                $messages = collect($check['blockingReasons'])->pluck('message')->implode(' | ');
                throw new \RuntimeException("No se puede cerrar la producción: {$messages}");
            }

            $production->update([
                'closed_at' => now('UTC'),
                'closed_by' => $user->id,
                'closure_reason' => $reason,
            ]);

            return $production->fresh();
        });
    }

    /**
     * Reabre una producción cerrada.
     * Solo roles administrador o superior.
     */
    public function reopen(Production $production, User $user, string $reason): Production
    {
        return DB::transaction(function () use ($production, $user, $reason) {
            $production = Production::lockForUpdate()->findOrFail($production->id);

            if (! $production->isClosed()) {
                throw new \RuntimeException('La producción no está cerrada, no se puede reabrir.');
            }

            $production->update([
                'closed_at' => null,
                'closed_by' => null,
                'closure_reason' => null,
                'reopened_at' => now('UTC'),
                'reopened_by' => $user->id,
                'reopen_reason' => $reason,
            ]);

            return $production->fresh();
        });
    }

    // =========================================================
    // PRIVADOS
    // =========================================================

    private function buildResult(bool $canClose, array $reasons, array $summary): array
    {
        return [
            'canClose' => $canClose,
            'blockingReasons' => $reasons,
            'summary' => $summary,
        ];
    }

    private function buildSummary(Production $production): array
    {
        $lot = $production->lot;

        $producedWeight = (float) $production->allOutputs()
            ->whereHas('productionRecord', function ($q) {
                $q->whereDoesntHave('children');
            })
            ->sum('weight_kg');

        $producedBoxes = (int) $production->allOutputs()
            ->whereHas('productionRecord', function ($q) {
                $q->whereDoesntHave('children');
            })
            ->sum('boxes');

        $salesWeight = (float) Box::query()
            ->where('lot', $lot)
            ->whereDoesntHave('productionInputs')
            ->whereHas('palletBox.pallet', function ($q) {
                $q->where('status', Pallet::STATE_SHIPPED)->whereNotNull('order_id');
            })
            ->sum('net_weight');

        $reprocessedWeight = (float) Box::query()
            ->where('lot', $lot)
            ->whereHas('productionInputs')
            ->sum('net_weight');

        $stockWeight = (float) Box::query()
            ->where('lot', $lot)
            ->whereDoesntHave('productionInputs')
            ->whereHas('palletBox.pallet', function ($q) {
                $q->inStock()->whereNull('order_id');
            })
            ->sum('net_weight');

        return [
            'producedWeight' => round($producedWeight, 2),
            'producedBoxes' => $producedBoxes,
            'salesWeight' => round($salesWeight, 2),
            'reprocessedWeight' => round($reprocessedWeight, 2),
            'stockWeight' => round($stockWeight, 2),
            'balanceWeight' => round($producedWeight - $salesWeight - $reprocessedWeight - $stockWeight, 2),
        ];
    }

    /**
     * Verifica si los únicos errores acumulados son independientes de la conciliación,
     * para no ejecutarla inútilmente cuando ya hay bloqueos estructurales graves.
     * La conciliación siempre se ejecuta si no hay otros errores.
     */
    private function onlyHasNonReconciliationErrors(array $reasons): bool
    {
        $nonReconciliationCodes = [
            'already_closed', 'not_open', 'no_processes',
            'process_not_started', 'process_not_finished', 'final_node_no_outputs',
        ];

        foreach ($reasons as $reason) {
            if (! in_array($reason['code'], $nonReconciliationCodes)) {
                return false;
            }
        }

        return true;
    }
}
