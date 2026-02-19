<?php

namespace App\Services\v2;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

class OrderUpdateService
{
    /**
     * Actualiza un pedido con los datos validados. Si status pasa a 'finished', marca palets como shipped.
     *
     * @param array<string, mixed> $validated Datos validados (UpdateOrderRequest)
     * @return Order Pedido actualizado con relaciones cargadas
     * @throws ValidationException Si entry_date > load_date
     */
    public static function update(Order $order, array $validated): Order
    {
        $entryDate = array_key_exists('entryDate', $validated) ? $validated['entryDate'] : $order->entry_date;
        $loadDate = array_key_exists('loadDate', $validated) ? $validated['loadDate'] : $order->load_date;

        if ($entryDate && $loadDate && $entryDate > $loadDate) {
            throw ValidationException::withMessages([
                'loadDate' => ['La fecha de carga debe ser mayor o igual a la fecha de entrada.'],
            ]);
        }

        if (array_key_exists('buyerReference', $validated)) {
            $order->buyer_reference = $validated['buyerReference'];
        }
        if (array_key_exists('payment', $validated)) {
            $order->payment_term_id = $validated['payment'];
        }
        if (array_key_exists('billingAddress', $validated)) {
            $order->billing_address = $validated['billingAddress'];
        }
        if (array_key_exists('shippingAddress', $validated)) {
            $order->shipping_address = $validated['shippingAddress'];
        }
        if (array_key_exists('transportationNotes', $validated)) {
            $order->transportation_notes = $validated['transportationNotes'];
        }
        if (array_key_exists('productionNotes', $validated)) {
            $order->production_notes = $validated['productionNotes'];
        }
        if (array_key_exists('accountingNotes', $validated)) {
            $order->accounting_notes = $validated['accountingNotes'];
        }
        if (array_key_exists('salesperson', $validated)) {
            $order->salesperson_id = $validated['salesperson'];
        }
        if (array_key_exists('transport', $validated)) {
            $order->transport_id = $validated['transport'];
        }
        if (array_key_exists('entryDate', $validated)) {
            $order->entry_date = $validated['entryDate'];
        }
        if (array_key_exists('loadDate', $validated)) {
            $order->load_date = $validated['loadDate'];
        }
        if (array_key_exists('status', $validated)) {
            $previousStatus = $order->status;
            $order->status = $validated['status'];
            if ($validated['status'] === 'finished' && $previousStatus !== 'finished') {
                $order->load('pallets');
                foreach ($order->pallets as $pallet) {
                    $pallet->changeToShipped();
                }
            }
        }
        if (array_key_exists('incoterm', $validated)) {
            $order->incoterm_id = $validated['incoterm'];
        }
        if (array_key_exists('truckPlate', $validated)) {
            $order->truck_plate = $validated['truckPlate'];
        }
        if (array_key_exists('trailerPlate', $validated)) {
            $order->trailer_plate = $validated['trailerPlate'];
        }
        if (array_key_exists('temperature', $validated)) {
            $order->temperature = $validated['temperature'];
        }
        if (array_key_exists('emails', $validated) || array_key_exists('ccEmails', $validated)) {
            $order->emails = self::formatEmails(
                $validated['emails'] ?? [],
                $validated['ccEmails'] ?? []
            );
        }
        if (array_key_exists('orderType', $validated)) {
            $order->order_type = $validated['orderType'];
        }

        $order->updated_at = now();
        $order->save();

        $order->load([
            'pallets.boxes.box.productionInputs',
            'pallets.boxes.box.product.species.fishingGear',
        ]);

        return $order;
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
