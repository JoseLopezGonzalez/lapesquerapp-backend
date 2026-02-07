<?php

namespace App\Http\Resources\v2;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailsResource extends JsonResource
{


    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'buyerReference' => $this->buyer_reference,
            'customer' => $this->customer?->toArrayAssoc(),
            'paymentTerm' => $this->payment_term?->toArrayAssoc(),
            'billingAddress' => $this->billing_address,
            'shippingAddress' => $this->shipping_address,
            'transportationNotes' => $this->transportation_notes,
            'productionNotes' => $this->production_notes,
            'accountingNotes' => $this->accounting_notes,
            'salesperson' => $this->salesperson?->toArrayAssoc(),
            'transport' => $this->transport?->toArrayAssoc(),
            'entryDate' => $this->entry_date,
            'loadDate' => $this->load_date,
            'status' => $this->status,
            'pallets' => $this->pallets->map(function ($pallet) {
                return $pallet->toArrayAssoc();
            }),
            'incoterm' => $this->incoterm?->toArrayAssoc(),
            'totalNetWeight' => $this->totalNetWeight,
            'numberOfPallets' => $this->numberOfPallets,
            'totalBoxes' => $this->totalBoxes,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'plannedProductDetails' => $this->plannedProductDetails
                ? $this->plannedProductDetails->map(function ($detail) {
                    return $detail->toArrayAssoc();
                })
                : null,
            'productionProductDetails' => $this->productionProductDetails,
            'productDetails' => $this->productDetails,
            'subTotalAmount' => $this->subTotalAmount,
            'totalAmount' => $this->totalAmount,
            'emails' => $this->emailsArray,
            'ccEmails' => $this->ccEmailsArray,
            'truckPlate' => $this->truck_plate,
            'trailerPlate' => $this->trailer_plate,
            'temperature' => $this->temperature,
            'incident' => $this->incident ? $this->incident->toArrayAssoc() : null,
            'customerHistory' => $this->getCustomerHistory(),
        ];
    }

    private function getCustomerHistory()
    {
        // Obtener pedidos anteriores del mismo cliente excluyendo el actual
        $previousOrders = Order::where('customer_id', $this->customer_id)
            ->where('id', '<>', $this->id)
            ->with('plannedProductDetails.product', 'pallets.boxes.box.product')
            ->orderBy('load_date', 'desc')
            ->get();

        $history = [];

        foreach ($previousOrders as $prevOrder) {
            // Aquí usamos el atributo dinámico productDetails ya implementado
            foreach ($prevOrder->productDetails as $detail) {
                $productId = $detail['product']['id'];

                if (!isset($history[$productId])) {
                    $history[$productId] = [
                        'product' => $detail['product'],
                        'total_boxes' => 0,
                        'total_net_weight' => 0,
                        'average_unit_price' => 0,
                        'last_order_date' => $prevOrder->load_date,
                        'lines' => [],
                        'total_amount' => 0,
                    ];
                }

                // Actualizar valores acumulados
                $history[$productId]['total_boxes'] += $detail['boxes'];
                $history[$productId]['total_net_weight'] += $detail['netWeight'];
                $history[$productId]['total_amount'] += $detail['subtotal'];

                // Registrar línea individual
                $history[$productId]['lines'][] = [
                    'order_id' => $prevOrder->id,
                    'formatted_id' => $prevOrder->formatted_id,
                    'load_date' => $prevOrder->load_date,
                    'boxes' => $detail['boxes'],
                    'net_weight' => round((float) $detail['netWeight'], 2),
                    'unit_price' => $detail['unitPrice'],
                    'subtotal' => $detail['subtotal'],
                    'total' => $detail['total'],
                ];

                // Actualizar la última fecha de pedido si es más reciente
                if ($prevOrder->load_date > $history[$productId]['last_order_date']) {
                    $history[$productId]['last_order_date'] = $prevOrder->load_date;
                }
            }
        }

        // Finalmente, calcular el precio medio ponderado por kg de producto
        foreach ($history as &$product) {
            if ($product['total_net_weight'] > 0) {
                $product['average_unit_price'] = round($product['total_amount'] / $product['total_net_weight'], 2);
            } else {
                $product['average_unit_price'] = 0;
            }
        }

        // Reindexar para devolver un array limpio
        return array_values($history);
    }
}
