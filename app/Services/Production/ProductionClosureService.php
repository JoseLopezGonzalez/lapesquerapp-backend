<?php

namespace App\Services\Production;

use App\Models\Box;
use App\Models\Incident;
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
                $reasons[] = $this->blockingReason(
                    'already_closed',
                    'Producción ya cerrada',
                    'Esta producción ya está cerrada definitivamente.',
                    'No hace falta volver a cerrarla.'
                );
            } else {
                $reasons[] = $this->blockingReason(
                    'not_open',
                    'Producción no abierta',
                    'Esta producción todavía no está abierta.',
                    'Abre la producción antes de intentar el cierre definitivo.'
                );
            }

            return $this->buildResult(false, $reasons, $this->buildSummary($production));
        }

        // 2. Debe tener al menos un proceso
        $records = $production->records()->with(['outputs', 'children'])->get();
        if ($records->isEmpty()) {
            $reasons[] = $this->blockingReason(
                'no_processes',
                'Producción sin procesos',
                'No hay procesos registrados en esta producción.',
                'Añade al menos un proceso antes de cerrar el lote.'
            );
        }

        // 3 y 4. Todos los procesos del árbol deben tener started_at y finished_at
        $allRecords = $production->records()->with('process')->get();
        foreach ($allRecords as $record) {
            if ($record->started_at === null) {
                $processLabel = $this->processLabel($record);
                $reasons[] = $this->blockingReason(
                    'process_not_started',
                    'Proceso sin iniciar',
                    "{$processLabel} no tiene fecha de inicio.",
                    'Completa la fecha de inicio del proceso.',
                    [
                        'recordId' => $record->id,
                        'processName' => $record->process?->name,
                        'entityLabel' => $processLabel,
                    ],
                );
            }
            if ($record->finished_at === null) {
                $processLabel = $this->processLabel($record);
                $reasons[] = $this->blockingReason(
                    'process_not_finished',
                    'Proceso sin finalizar',
                    "{$processLabel} no tiene fecha de finalización.",
                    'Completa la fecha de fin del proceso.',
                    [
                        'recordId' => $record->id,
                        'processName' => $record->process?->name,
                        'entityLabel' => $processLabel,
                    ],
                );
            }
        }

        // 5. Cada nodo final debe tener al menos un output
        $finalRecords = $production->records()
            ->whereDoesntHave('children')
            ->whereDoesntHave('outputs')
            ->whereDoesntHave('parentOutputConsumptions')
            ->with('process')
            ->get();

        foreach ($finalRecords as $record) {
            if ($record->outputs()->count() === 0) {
                $processLabel = $this->processLabel($record);
                $reasons[] = $this->blockingReason(
                    'final_node_no_outputs',
                    'Proceso final sin producción',
                    "{$processLabel} es un proceso final y no tiene salidas registradas.",
                    'Registra la producción obtenida en ese proceso final.',
                    [
                        'recordId' => $record->id,
                        'processName' => $record->process?->name,
                        'entityLabel' => $processLabel,
                    ],
                );
            }
        }

        // 6. No debe haber pedidos pendientes o con incidencia abierta con palets de este lote
        $pendingOrders = Order::query()
            ->with('incident')
            ->where(function ($q) {
                $q->where('status', Order::STATUS_PENDING)
                    ->orWhere(function ($q) {
                        $q->where('status', Order::STATUS_INCIDENT)
                            ->where(function ($q) {
                                $q->whereDoesntHave('incident')
                                    ->orWhereHas('incident', function ($q) {
                                        $q->where('status', Incident::STATUS_OPEN);
                                    });
                            });
                    });
            })
            ->whereHas('pallets.boxes.box', function ($q) use ($lot) {
                $q->where('lot', $lot)->whereDoesntHave('productionInputs');
            })
            ->get();

        foreach ($pendingOrders as $order) {
            $statusLabel = $this->orderStatusLabel($order->status);
            $reasons[] = $this->blockingReason(
                'pending_order',
                'Pedido no cerrado',
                "El pedido {$order->formattedId} está {$statusLabel} y contiene palets de este lote.",
                'Finaliza o resuelve el pedido antes de cerrar la producción.',
                [
                    'orderId' => $order->id,
                    'orderStatus' => $order->status,
                    'orderStatusLabel' => $statusLabel,
                    'entityLabel' => "Pedido {$order->formattedId}",
                ],
            );
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
            $statusLabel = $this->palletStatusLabel($pallet->status);
            $reasons[] = $this->blockingReason(
                'pallet_not_shipped',
                'Palet pendiente de envío',
                "El palet #{$pallet->id} está asignado a un pedido, pero sigue {$statusLabel}.",
                'Marca el palet como enviado o revisa su pedido.',
                [
                    'palletId' => $pallet->id,
                    'palletStatus' => $pallet->status,
                    'palletStatusLabel' => $statusLabel,
                    'entityLabel' => "Palet #{$pallet->id}",
                ],
            );
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
            $reasons[] = $this->blockingReason(
                'stock_remaining',
                'Stock pendiente del lote',
                "El palet #{$pallet->id} todavía tiene cajas de este lote en stock.",
                'Vende, reprocesa o regulariza las cajas antes de cerrar el lote.',
                [
                    'palletId' => $pallet->id,
                    'palletStatus' => $pallet->status,
                    'palletStatusLabel' => $this->palletStatusLabel($pallet->status),
                    'entityLabel' => "Palet #{$pallet->id}",
                ],
            );
        }

        // 10. No debe haber cajas del lote sin ningún destino (ni palet, ni reprocesadas)
        $orphanBoxes = Box::query()
            ->where('lot', $lot)
            ->whereDoesntHave('productionInputs')
            ->whereDoesntHave('palletBox')
            ->get();

        foreach ($orphanBoxes as $box) {
            $reasons[] = $this->blockingReason(
                'orphan_box',
                'Caja sin destino',
                "La caja #{$box->id} del lote no tiene destino: no está en un palet ni fue reprocesada.",
                'Ubica la caja en un palet o asígnala a un reproceso.',
                [
                    'boxId' => $box->id,
                    'entityLabel' => "Caja #{$box->id}",
                ],
            );
        }

        // 11. La conciliación debe estar en 'ok' (warning y error bloquean)
        if ($reasons === [] || $this->onlyHasNonReconciliationErrors($reasons)) {
            $reconciliation = $production->getDetailedReconciliationByProduct();
            $overallStatus = $reconciliation['summary']['overallStatus'] ?? 'error';

            if ($overallStatus !== 'ok') {
                $statusLabel = $this->reconciliationStatusLabel($overallStatus);
                $reasons[] = $this->blockingReason(
                    'reconciliation_not_ok',
                    'Conciliación pendiente',
                    "La conciliación del lote está {$statusLabel}. Solo se puede cerrar cuando esté correcta.",
                    'Revisa el balance del lote y corrige las diferencias antes de cerrar.',
                    [
                        'reconciliationStatus' => $overallStatus,
                        'reconciliationStatusLabel' => $statusLabel,
                    ],
                );
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

    private function blockingReason(string $code, string $title, string $message, string $description, array $extra = []): array
    {
        return array_merge([
            'code' => $code,
            'label' => $title,
            'title' => $title,
            'message' => $message,
            'description' => $description,
            'action' => $description,
        ], $extra);
    }

    private function processLabel($record): string
    {
        $name = $record->process?->name;

        if ($name === null || trim($name) === '') {
            return "Proceso #{$record->id}";
        }

        return "{$name} (proceso #{$record->id})";
    }

    private function orderStatusLabel(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING => 'pendiente',
            Order::STATUS_FINISHED => 'finalizado',
            Order::STATUS_INCIDENT => 'con incidencia abierta',
            default => "en estado {$status}",
        };
    }

    private function palletStatusLabel(int|string|null $status): string
    {
        return match ((int) $status) {
            Pallet::STATE_REGISTERED => 'registrado',
            Pallet::STATE_STORED => 'almacenado',
            Pallet::STATE_SHIPPED => 'enviado',
            Pallet::STATE_PROCESSED => 'procesado',
            default => 'en un estado no reconocido',
        };
    }

    private function reconciliationStatusLabel(string $status): string
    {
        return match ($status) {
            'ok' => 'correcta',
            'warning' => 'con avisos',
            'error' => 'con errores',
            default => "en estado {$status}",
        };
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
