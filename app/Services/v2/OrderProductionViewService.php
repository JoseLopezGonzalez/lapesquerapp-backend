<?php

namespace App\Services\v2;

use App\Models\Order;
use Carbon\Carbon;

class OrderProductionViewService
{
    /**
     * Vista de producción: pedidos de la fecha dada agrupados por producto.
     * Misma lógica que OrderController::productionView().
     *
     * @param Carbon|null $date Fecha de carga (por defecto hoy)
     * @return array{data: array<int, array{id: int, name: string, orders: array<int, array>}>}
     */
    public static function getData(?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();

        $orders = Order::whereDate('load_date', $date)
            ->with([
                'plannedProductDetails' => function ($q) {
                    $q->select(['id', 'order_id', 'product_id', 'quantity', 'boxes']);
                },
                'plannedProductDetails.product' => function ($q) {
                    $q->select(['id', 'name']);
                },
                'pallets' => function ($q) {
                    $q->select(['id', 'order_id']);
                },
                'pallets.boxes' => function ($q) {
                    $q->select(['id', 'pallet_id', 'box_id']);
                },
                'pallets.boxes.box' => function ($q) {
                    $q->select(['id', 'article_id', 'net_weight']);
                },
                'pallets.boxes.box.productionInputs' => function ($q) {
                    $q->select(['id', 'box_id']);
                },
            ])
            ->get();

        $productsData = [];

        foreach ($orders as $order) {
            foreach ($order->plannedProductDetails as $plannedDetail) {
                $productId = $plannedDetail->product_id;
                $productName = $plannedDetail->product->name ?? 'Producto sin nombre';

                if (!isset($productsData[$productId])) {
                    $productsData[$productId] = [
                        'id' => $productId,
                        'name' => $productName,
                        'orders' => [],
                    ];
                }

                $completedQuantity = 0;
                $completedBoxes = 0;
                $palletIds = [];

                foreach ($order->pallets as $pallet) {
                    $palletHasProduct = false;

                    foreach ($pallet->boxes as $palletBox) {
                        if ($palletBox->box && $palletBox->box->article_id == $productId) {
                            $isAvailable = $palletBox->box->productionInputs->isEmpty();

                            if ($isAvailable) {
                                $completedQuantity += $palletBox->box->net_weight ?? 0;
                                $completedBoxes++;
                                $palletHasProduct = true;
                            }
                        }
                    }

                    if ($palletHasProduct && !in_array($pallet->id, $palletIds)) {
                        $palletIds[] = $pallet->id;
                    }
                }

                $plannedQuantity = (float) $plannedDetail->quantity;
                $plannedBoxes = (int) $plannedDetail->boxes;
                $remainingQuantity = $plannedQuantity - $completedQuantity;
                $remainingBoxes = $plannedBoxes - $completedBoxes;

                if ($completedBoxes == $plannedBoxes) {
                    $status = 'completed';
                } elseif ($completedBoxes > $plannedBoxes) {
                    $status = 'exceeded';
                } else {
                    $status = 'pending';
                }

                $productsData[$productId]['orders'][] = [
                    'orderId' => $order->id,
                    'quantity' => $plannedQuantity,
                    'boxes' => $plannedBoxes,
                    'completedQuantity' => round($completedQuantity, 2),
                    'completedBoxes' => $completedBoxes,
                    'remainingQuantity' => round($remainingQuantity, 2),
                    'remainingBoxes' => $remainingBoxes,
                    'palets' => $palletIds,
                    'status' => $status,
                ];
            }
        }

        $result = array_values($productsData);
        usort($result, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return ['data' => $result];
    }
}
