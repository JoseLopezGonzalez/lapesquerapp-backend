<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\StoreOrderAuxiliaryLineRequest;
use App\Http\Requests\v2\UpdateOrderAuxiliaryLineRequest;
use App\Http\Resources\v2\OrderAuxiliaryLineResource;
use App\Models\Order;
use App\Models\OrderAuxiliaryLine;
use Illuminate\Http\JsonResponse;

class OrderAuxiliaryLineController extends Controller
{
    public function index(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $lines = $order->auxiliaryLines()
            ->with(['auxiliaryProduct', 'tax'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => OrderAuxiliaryLineResource::collection($lines),
        ]);
    }

    public function store(StoreOrderAuxiliaryLineRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validated();

        $line = new OrderAuxiliaryLine([
            'order_id' => $order->id,
            'auxiliary_product_id' => $validated['auxiliaryProductId'] ?? null,
            'description' => $validated['description'] ?? null,
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'],
            'unit_price' => $validated['unitPrice'],
            'tax_id' => $validated['taxId'] ?? null,
        ]);
        $line->save();

        $line->load(['auxiliaryProduct', 'tax']);

        return response()->json([
            'message' => 'Línea auxiliar creada correctamente.',
            'data' => new OrderAuxiliaryLineResource($line),
        ], 201);
    }

    public function update(UpdateOrderAuxiliaryLineRequest $request, Order $order, OrderAuxiliaryLine $line): JsonResponse
    {
        $this->authorize('update', $order);
        $this->ensureLineBelongsToOrder($order, $line);

        $validated = $request->validated();

        if (array_key_exists('auxiliaryProductId', $validated)) {
            $line->auxiliary_product_id = $validated['auxiliaryProductId'];
        }
        if (array_key_exists('description', $validated)) {
            $line->description = $validated['description'];
        }
        if (array_key_exists('quantity', $validated)) {
            $line->quantity = $validated['quantity'];
        }
        if (array_key_exists('unit', $validated)) {
            $line->unit = $validated['unit'];
        }
        if (array_key_exists('unitPrice', $validated)) {
            $line->unit_price = $validated['unitPrice'];
        }
        if (array_key_exists('taxId', $validated)) {
            $line->tax_id = $validated['taxId'];
        }

        $line->save();
        $line->load(['auxiliaryProduct', 'tax']);

        return response()->json([
            'message' => 'Línea auxiliar actualizada correctamente.',
            'data' => new OrderAuxiliaryLineResource($line),
        ]);
    }

    public function destroy(Order $order, OrderAuxiliaryLine $line): JsonResponse
    {
        $this->authorize('update', $order);
        $this->ensureLineBelongsToOrder($order, $line);

        $line->delete();

        return response()->json(['message' => 'Línea auxiliar eliminada correctamente.']);
    }

    private function ensureLineBelongsToOrder(Order $order, OrderAuxiliaryLine $line): void
    {
        abort_unless($line->order_id === $order->id, 404, 'La línea auxiliar no pertenece a este pedido.');
    }
}
