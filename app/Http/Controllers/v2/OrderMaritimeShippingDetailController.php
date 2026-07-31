<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\UpdateOrderMaritimeShippingDetailRequest;
use App\Http\Resources\v2\OrderMaritimeShippingDetailResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderMaritimeShippingDetailController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $detail = $order->maritimeShippingDetail()->with('customsBroker')->first();

        if (! $detail) {
            return response()->json([
                'message' => 'Datos de envío marítimo no encontrados.',
                'userMessage' => 'Este pedido todavía no tiene datos de envío marítimo.',
            ], 404);
        }

        return response()->json(['data' => new OrderMaritimeShippingDetailResource($detail)]);
    }

    /**
     * Crea o actualiza (upsert) los datos de envío marítimo del pedido. Relación 1:1 opcional.
     */
    public function update(UpdateOrderMaritimeShippingDetailRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validated();

        $detail = $order->maritimeShippingDetail()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'shipping_line' => $validated['shippingLine'] ?? null,
                'vessel_name' => $validated['vesselName'] ?? null,
                'voyage_number' => $validated['voyageNumber'] ?? null,
                'export_invoice_number' => $validated['exportInvoiceNumber'] ?? null,
                'booking_number' => $validated['bookingNumber'] ?? null,
                'swb_number' => $validated['swbNumber'] ?? null,
                'loading_port' => $validated['loadingPort'] ?? null,
                'discharge_port' => $validated['dischargePort'] ?? null,
                'origin_country' => $validated['originCountry'] ?? null,
                'destination_country' => $validated['destinationCountry'] ?? null,
                'customs_broker_id' => $validated['customsBrokerId'] ?? null,
                'ultimate_consignee_name' => $validated['ultimateConsigneeName'] ?? null,
                'ultimate_consignee_address' => $validated['ultimateConsigneeAddress'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Datos de envío marítimo guardados correctamente.',
            'data' => new OrderMaritimeShippingDetailResource($detail->load('customsBroker')),
        ]);
    }
}
