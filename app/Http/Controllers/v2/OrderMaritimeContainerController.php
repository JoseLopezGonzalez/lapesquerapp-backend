<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
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

    private function ensureContainerBelongsToOrder(Order $order, OrderMaritimeContainer $container): void
    {
        abort_unless($container->order_id === $order->id, 404, 'El contenedor no pertenece a este pedido.');
    }
}
