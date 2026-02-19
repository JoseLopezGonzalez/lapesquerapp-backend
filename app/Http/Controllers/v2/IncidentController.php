<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\StoreIncidentRequest;
use App\Http\Requests\v2\UpdateIncidentRequest;
use App\Models\Incident;
use App\Models\Order;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function show($orderId)
    {
        $order = Order::with('incident')->findOrFail($orderId);
        $this->authorize('view', $order);

        if (!$order->incident) {
            return response()->json([
                'message' => 'Incidencia no encontrada.',
                'userMessage' => 'No se encontró incidencia para este pedido.'
            ], 404);
        }

        return response()->json($order->incident);
    }

    public function store(StoreIncidentRequest $request, $orderId)
    {
        $order = Order::with('incident')->findOrFail($orderId);
        $this->authorize('update', $order);

        if ($order->incident) {
            return response()->json([
                'message' => 'La incidencia ya existe.',
                'userMessage' => 'Este pedido ya tiene una incidencia registrada.'
            ], 400);
        }

        $validated = $request->validated();
        $incident = Incident::create([
            'order_id' => $order->id,
            'description' => $validated['description'],
            'status' => 'open',
        ]);

        $order->markAsIncident();

        return response()->json($incident->toArrayAssoc(), 201);
    }

    public function update(UpdateIncidentRequest $request, $orderId)
    {
        $order = Order::with('incident')->findOrFail($orderId);
        $this->authorize('update', $order);

        $incident = $order->incident;

        if (!$incident) {
            return response()->json([
                'message' => 'Incidencia no encontrada.',
                'userMessage' => 'No se encontró incidencia para este pedido.'
            ], 404);
        }

        $validated = $request->validated();
        $incident->update([
            'status' => 'resolved',
            'resolution_type' => $validated['resolution_type'],
            'resolution_notes' => $validated['resolution_notes'] ?? null,
            'resolved_at' => now('UTC'),
        ]);

        return response()->json($incident->toArrayAssoc());
    }

    public function destroy($orderId)
    {
        $order = Order::with('incident')->findOrFail($orderId);
        $this->authorize('update', $order);

        $incident = $order->incident;

        if (!$incident) {
            return response()->json([
                'message' => 'Incidencia no encontrada.',
                'userMessage' => 'No se encontró incidencia para este pedido.'
            ], 404);
        }

        $incident->delete();
        $order->finalizeAfterIncident();

        return response()->json(['message' => 'Incident deleted'], 200);
    }
}
