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
            return response()->json(['message' => 'Incident not found'], 404);
        }

        return response()->json($order->incident);
    }

    public function store(Request $request, $orderId)
    {
        $order = Order::with('incident')->findOrFail($orderId);

        if ($order->incident) {
            return response()->json(['message' => 'Incident already exists'], 400);
        }

        $validated = $request->validate([
            'description' => 'required|string',
        ]);

        $incident = Incident::create([
            'order_id' => $order->id,
            'description' => $validated['description'],
            'status' => 'open',
        ]);

        $order->update(['status' => 'incident']);

        return response()->json($incident->toArrayAssoc(), 201);
    }

    public function update(Request $request, $orderId)
    {
        $order = Order::with('incident')->findOrFail($orderId);

        $incident = $order->incident;

        if (!$incident) {
            return response()->json(['message' => 'Incident not found'], 404);
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
            return response()->json(['message' => 'Incident not found'], 404);
        }

        $incident->delete();

        /* cambiar order status a finished */
        $order->status = 'finished';
        $order->save();
        
        // Cambiar todos los palets del pedido a 'shipped'
        $order->load('pallets');
        foreach ($order->pallets as $pallet) {
            $pallet->changeToShipped();
        }

        /* return response()->noContent(); */
        /* return mensaje satisfactorio */
        return response()->json(['message' => 'Incident deleted'], 200);
    }
}
