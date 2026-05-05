<?php

namespace App\Services\Production;

use App\Models\Box;
use App\Models\Order;
use App\Models\Pallet;
use App\Models\Production;

class ProductionLotLockService
{
    /**
     * Lanza excepción si el lote está bloqueado (producción cerrada definitivamente).
     */
    public function assertLotIsMutable(?string $lot, string $operation): void
    {
        if ($lot === null || $lot === '') {
            return;
        }

        if ($this->isLotLocked($lot)) {
            throw new \RuntimeException(
                "No se puede ejecutar '{$operation}': el lote '{$lot}' pertenece a una producción cerrada definitivamente. Reabre la producción antes de realizar cambios."
            );
        }
    }

    /**
     * Lanza excepción si la caja pertenece a un lote bloqueado.
     */
    public function assertBoxIsMutable(Box $box, string $operation): void
    {
        $this->assertLotIsMutable($box->lot, $operation);
    }

    /**
     * Lanza excepción si el palet contiene cajas editables de un lote bloqueado.
     * Las cajas gastadas (usadas en producción) quedan excluidas: ya no pueden
     * ser modificadas ni eliminadas por sus propias reglas de modelo.
     */
    public function assertPalletIsMutable(Pallet $pallet, string $operation): void
    {
        $lockedLots = $pallet->boxes()
            ->whereHas('box', fn ($q) => $q->whereDoesntHave('productionInputs'))
            ->with('box:id,lot')
            ->get()
            ->pluck('box.lot')
            ->filter()
            ->unique()
            ->filter(fn ($lot) => $this->isLotLocked($lot));

        if ($lockedLots->isNotEmpty()) {
            $lots = $lockedLots->implode(', ');
            throw new \RuntimeException(
                "No se puede ejecutar '{$operation}': el palet #{$pallet->id} contiene cajas de lotes cerrados ({$lots}). Reabre las producciones antes de realizar cambios."
            );
        }
    }

    /**
     * Lanza excepción si el pedido contiene palets con lotes bloqueados.
     */
    public function assertOrderIsMutableForProductionLots(Order $order, string $operation): void
    {
        $lockedLots = \App\Models\Box::query()
            ->whereHas('palletBox.pallet', fn ($q) => $q->where('order_id', $order->id))
            ->whereDoesntHave('productionInputs')
            ->pluck('lot')
            ->filter()
            ->unique()
            ->filter(fn ($lot) => $this->isLotLocked($lot));

        if ($lockedLots->isNotEmpty()) {
            $lots = $lockedLots->implode(', ');
            throw new \RuntimeException(
                "No se puede ejecutar '{$operation}': el pedido #{$order->id} contiene cajas de lotes cerrados ({$lots}). Reabre las producciones antes de realizar cambios."
            );
        }
    }

    /**
     * Comprueba si un lote está bloqueado (tiene producción cerrada).
     */
    public function isLotLocked(string $lot): bool
    {
        return Production::where('lot', trim($lot))
            ->whereNotNull('closed_at')
            ->exists();
    }
}
