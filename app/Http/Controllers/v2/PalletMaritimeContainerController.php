<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\UpdatePalletMaritimeContainerRequest;
use App\Models\Order;
use App\Models\OrderMaritimeContainer;
use App\Models\Pallet;
use Illuminate\Http\JsonResponse;

class PalletMaritimeContainerController extends Controller
{
    /**
     * Asigna o desasigna (containerId = null) un único palet a un contenedor marítimo del pedido.
     */
    public function update(UpdatePalletMaritimeContainerRequest $request, Order $order, Pallet $pallet): JsonResponse
    {
        $this->authorize('update', $order);
        abort_unless($pallet->order_id === $order->id, 404, 'El palet no pertenece a este pedido.');

        $validated = $request->validated();
        $containerId = $validated['containerId'] ?? null;

        if ($containerId !== null) {
            $container = OrderMaritimeContainer::find($containerId);
            abort_unless($container && $container->order_id === $order->id, 404, 'El contenedor no pertenece a este pedido.');
        }

        $pallet->order_maritime_container_id = $containerId;
        $pallet->save();

        $pallet->load(['boxes.box.product.species', 'boxes.box.product.captureZone', 'storedPallet']);

        return response()->json([
            'message' => $containerId
                ? 'Palet asignado al contenedor correctamente.'
                : 'Palet desasignado del contenedor correctamente.',
            'data' => $pallet->toArrayAssocV2(),
        ]);
    }
}
