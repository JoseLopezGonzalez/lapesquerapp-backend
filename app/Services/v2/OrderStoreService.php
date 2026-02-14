<?php

namespace App\Services\v2;

use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use Illuminate\Support\Facades\DB;

class OrderStoreService
{
    /**
     * Crea un pedido y sus líneas planificadas. Transacción única.
     *
     * @param array<string, mixed> $validated Datos validados (StoreOrderRequest)
     * @return Order Pedido creado con relaciones cargadas para OrderDetailsResource
     * @throws \Exception
     */
    public static function store(array $validated): Order
    {
        $formattedEmails = self::formatEmails(
            $validated['emails'] ?? [],
            $validated['ccEmails'] ?? []
        );

        DB::beginTransaction();

        try {
            $order = Order::create([
                'customer_id' => $validated['customer'],
                'entry_date' => $validated['entryDate'],
                'load_date' => $validated['loadDate'],
                'salesperson_id' => $validated['salesperson'] ?? null,
                'payment_term_id' => $validated['payment'] ?? null,
                'incoterm_id' => $validated['incoterm'] ?? null,
                'buyer_reference' => $validated['buyerReference'] ?? null,
                'transport_id' => $validated['transport'] ?? null,
                'truck_plate' => $validated['truckPlate'] ?? null,
                'trailer_plate' => $validated['trailerPlate'] ?? null,
                'temperature' => $validated['temperature'] ?? null,
                'billing_address' => $validated['billingAddress'] ?? null,
                'shipping_address' => $validated['shippingAddress'] ?? null,
                'transportation_notes' => $validated['transportationNotes'] ?? null,
                'production_notes' => $validated['productionNotes'] ?? null,
                'accounting_notes' => $validated['accountingNotes'] ?? null,
                'emails' => $formattedEmails ?? '',
                'status' => 'pending',
            ]);

            if (!empty($validated['plannedProducts'])) {
                foreach ($validated['plannedProducts'] as $line) {
                    OrderPlannedProductDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $line['product'],
                        'tax_id' => $line['tax'],
                        'quantity' => $line['quantity'],
                        'boxes' => $line['boxes'],
                        'unit_price' => $line['unitPrice'],
                    ]);
                }
            }

            DB::commit();

            $order->load([
                'pallets.boxes.box.productionInputs',
                'pallets.boxes.box.product.species.fishingGear',
            ]);

            return $order;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private static function formatEmails(array $emails, array $ccEmails): ?string
    {
        $all = [];
        foreach ($emails as $email) {
            $all[] = trim($email);
        }
        foreach ($ccEmails as $email) {
            $all[] = 'CC:' . trim($email);
        }
        return count($all) > 0 ? implode(";\n", $all) . ';' : null;
    }
}
