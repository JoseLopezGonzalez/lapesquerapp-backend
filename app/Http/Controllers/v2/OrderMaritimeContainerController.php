<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\AssignOrderMaritimeContainerPalletsRequest;
use App\Http\Requests\v2\StoreOrderMaritimeContainerRequest;
use App\Http\Requests\v2\UpdateOrderMaritimeContainerRequest;
use App\Http\Resources\v2\OrderMaritimeContainerResource;
use App\Models\Order;
use App\Models\OrderMaritimeContainer;
use Illuminate\Http\JsonResponse;

class OrderMaritimeContainerController extends Controller
{
    public function index(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $containers = $order->maritimeContainers()
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => OrderMaritimeContainerResource::collection($containers),
        ]);
    }

    public function store(StoreOrderMaritimeContainerRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validated();

        $container = new OrderMaritimeContainer([
            'order_id' => $order->id,
            'container_number' => $validated['containerNumber'],
            'seal_number' => $validated['sealNumber'] ?? null,
        ]);
        $container->save();

        return response()->json([
            'message' => 'Contenedor añadido correctamente.',
            'data' => new OrderMaritimeContainerResource($container),
        ], 201);
    }

    public function update(UpdateOrderMaritimeContainerRequest $request, Order $order, OrderMaritimeContainer $container): JsonResponse
    {
        $this->authorize('update', $order);
        $this->ensureContainerBelongsToOrder($order, $container);

        $validated = $request->validated();

        if (array_key_exists('containerNumber', $validated)) {
            $container->container_number = $validated['containerNumber'];
        }
        if (array_key_exists('sealNumber', $validated)) {
            $container->seal_number = $validated['sealNumber'];
        }

        $container->save();

        return response()->json([
            'message' => 'Contenedor actualizado correctamente.',
            'data' => new OrderMaritimeContainerResource($container),
        ]);
    }

    public function destroy(Order $order, OrderMaritimeContainer $container): JsonResponse
    {
        $this->authorize('update', $order);
        $this->ensureContainerBelongsToOrder($order, $container);

        $container->delete();

        return response()->json(['message' => 'Contenedor eliminado correctamente.']);
    }

    public function pallets(Order $order, OrderMaritimeContainer $container): JsonResponse
    {
        $this->authorize('view', $order);
        $this->ensureContainerBelongsToOrder($order, $container);

        $pallets = $container->pallets()
            ->with(['boxes.box.product.species', 'boxes.box.product.captureZone', 'storedPallet'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => $pallets->map(fn ($pallet) => $pallet->toArrayAssocV2()),
        ]);
    }

    public function assignPallets(AssignOrderMaritimeContainerPalletsRequest $request, Order $order, OrderMaritimeContainer $container): JsonResponse
    {
        $this->authorize('update', $order);
        $this->ensureContainerBelongsToOrder($order, $container);

        $validated = $request->validated();

        $pallets = $order->pallets()->whereIn('id', $validated['palletIds'])->get();

        $foundIds = $pallets->pluck('id')->all();
        $missingIds = array_values(array_diff($validated['palletIds'], $foundIds));

        if (! empty($missingIds)) {
            return response()->json([
                'message' => 'Uno o más palets no pertenecen a este pedido.',
                'userMessage' => 'Uno o más palets seleccionados no pertenecen a este pedido: '.implode(', ', $missingIds),
            ], 422);
        }

        $order->pallets()->whereIn('id', $validated['palletIds'])->update([
            'order_maritime_container_id' => $container->id,
        ]);

        return response()->json([
            'message' => 'Palets asignados al contenedor correctamente.',
            'data' => new OrderMaritimeContainerResource($container->load('pallets')),
        ]);
    }

    public function unassignPallets(AssignOrderMaritimeContainerPalletsRequest $request, Order $order, OrderMaritimeContainer $container): JsonResponse
    {
        $this->authorize('update', $order);
        $this->ensureContainerBelongsToOrder($order, $container);

        $validated = $request->validated();

        $order->pallets()
            ->whereIn('id', $validated['palletIds'])
            ->where('order_maritime_container_id', $container->id)
            ->update(['order_maritime_container_id' => null]);

        return response()->json([
            'message' => 'Palets desasignados del contenedor correctamente.',
            'data' => new OrderMaritimeContainerResource($container->load('pallets')),
        ]);
    }

    private function ensureContainerBelongsToOrder(Order $order, OrderMaritimeContainer $container): void
    {
        abort_unless($container->order_id === $order->id, 404, 'El contenedor no pertenece a este pedido.');
    }
}
