<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Order;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function show($orderId)
    {
        $order = Order::with('incident')->findOrFail($orderId);

        if (!$order->incident) {
            return response()->json([
                'message' => 'Incidencia no encontrada.',
                'userMessage' => 'No se encontró incidencia para este pedido.'
            ], 404);
        }

        return response()->json($order->incident);
    }

    public function store(Request $request, $orderId)
    {
        $order = Order::with('incident')->findOrFail($orderId);

        if ($order->incident) {
            return response()->json([
                'message' => 'La incidencia ya existe.',
                'userMessage' => 'Este pedido ya tiene una incidencia registrada.'
            ], 400);
        }

        $validated = $request->validate([
            'description' => 'required|string',
        ]);

        $incident = Incident::create([
            'order_id' => $order->id,
            'description' => $validated['description'],
            'status' => 'open',
        ]);

        $order->markAsIncident();

        return response()->json($incident->toArrayAssoc(), 201);
    }

    public function update(Request $request, $orderId)
    {
        $order = Order::with('incident')->findOrFail($orderId);

        $incident = $order->incident;

        if (!$incident) {
            return response()->json([
                'message' => 'Incidencia no encontrada.',
                'userMessage' => 'No se encontró incidencia para este pedido.'
            ], 404);
        }

        $validated = $request->validate([
            'resolution_type' => 'required|in:returned,partially_returned,compensated',
            'resolution_notes' => 'nullable|string',
        ]);

        $incident->update([
            'status' => 'resolved',
            'resolution_type' => $validated['resolution_type'],
            'resolution_notes' => $validated['resolution_notes'] ?? null,
            'resolved_at' => now(),
        ]);

        return response()->json($incident->toArrayAssoc());
    }

    public function destroy($orderId)
    {
        $order = Order::with('incident')->findOrFail($orderId);

        $incident = $order->incident;

        if (!$incident) {
            return response()->json([
                'message' => 'Incidencia no encontrada.',
                'userMessage' => 'No se encontró incidencia para este pedido.'
            ], 404);
        }

        $incident->delete();

        // Finalizar el pedido y marcar palets como enviados
        $order->finalizeAfterIncident();

        /* return response()->noContent(); */
        /* return mensaje satisfactorio */
        return response()->json(['message' => 'Incident deleted'], 200);
    }
}
