<?php

namespace App\Services\v2;

use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use Illuminate\Support\Facades\DB;

class OperationalOrderUpdateService
{
    public static function update(Order $order, array $validated): Order
    {
        return DB::transaction(function () use ($order, $validated) {
            if (array_key_exists('status', $validated)) {
                $order->status = $validated['status'];
                if ($validated['status'] === Order::STATUS_FINISHED) {
                    $order->load('pallets');
                    foreach ($order->pallets as $pallet) {
                        $pallet->changeToShipped();
                    }
                }
                $order->save();
            }

            if (array_key_exists('plannedProducts', $validated)) {
                $order->plannedProductDetails()->delete();

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

            return OrderDetailService::getOrderForDetail((string) $order->id);
        });
    }
}
