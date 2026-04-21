<?php

namespace App\Services\v2;

use App\Models\Order;

class OrderCostAnalysisService
{
    public static function analyze(Order $order): array
    {
        $totalRevenue  = (float) $order->subtotal_amount;
        $totalCost     = $order->total_cost;
        $totalKg       = (float) $order->total_net_weight;
        $grossMargin   = $totalCost !== null ? round($totalRevenue - $totalCost, 2) : null;
        $marginPct     = ($grossMargin !== null && $totalRevenue > 0)
            ? round($grossMargin / $totalRevenue * 100, 2)
            : null;

        $revenuePerKg = ($totalKg > 0) ? round($totalRevenue / $totalKg, 4) : null;
        $costPerKg    = ($totalCost !== null && $totalKg > 0) ? round($totalCost / $totalKg, 4) : null;
        $marginPerKg  = ($grossMargin !== null && $totalKg > 0) ? round($grossMargin / $totalKg, 4) : null;

        return [
            'summary' => [
                'totalRevenue'     => round($totalRevenue, 2),
                'totalCost'        => $totalCost !== null ? round($totalCost, 2) : null,
                'grossMargin'      => $grossMargin,
                'marginPercentage' => $marginPct,
                'totalNetWeightKg' => round($totalKg, 3),
                'revenuePerKg'     => $revenuePerKg,
                'costPerKg'        => $costPerKg,
                'marginPerKg'      => $marginPerKg,
            ],
            'byProductLine' => self::buildByProductLine($order),
            'byPallet'      => self::buildByPallet($order),
        ];
    }

    private static function buildByProductLine(Order $order): array
    {
        $boxesByProduct = [];
        foreach ($order->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (! $box || ! $box->isAvailable) {
                    continue;
                }
                $boxesByProduct[$box->article_id][] = $box;
            }
        }

        $lines = [];
        foreach ($order->plannedProductDetails as $detail) {
            $productId  = $detail->product_id;
            $product    = $detail->product;
            $unitPrice  = (float) ($detail->unit_price ?? 0);
            $taxRate    = $detail->tax ? (float) $detail->tax->rate : 0;

            $boxes         = $boxesByProduct[$productId] ?? [];
            $lineWeightKg  = round(array_sum(array_map(fn ($b) => (float) $b->net_weight, $boxes)), 3);

            $lineCost = null;
            foreach ($boxes as $box) {
                $boxCost = $box->total_cost;
                if ($boxCost !== null) {
                    $lineCost = ($lineCost ?? 0.0) + $boxCost;
                }
            }

            $lineRevenue        = round($unitPrice * $lineWeightKg, 2);
            $lineRevenueWithTax = round($lineRevenue * (1 + $taxRate / 100), 2);
            $lineMargin         = $lineCost !== null ? round($lineRevenue - $lineCost, 2) : null;
            $lineMarginPct      = ($lineMargin !== null && $lineRevenue > 0)
                ? round($lineMargin / $lineRevenue * 100, 2)
                : null;
            $revenuePerKg       = $lineWeightKg > 0 ? round($lineRevenue / $lineWeightKg, 4) : null;
            $costPerKg          = ($lineCost !== null && $lineWeightKg > 0) ? round($lineCost / $lineWeightKg, 4) : null;
            $marginPerKg        = ($lineMargin !== null && $lineWeightKg > 0) ? round($lineMargin / $lineWeightKg, 4) : null;

            $lines[] = [
                'product' => [
                    'id'   => $product?->id,
                    'name' => $product?->name,
                ],
                'unitPrice'          => $unitPrice,
                'taxRate'            => $taxRate,
                'lineWeightKg'       => $lineWeightKg,
                'lineRevenue'        => $lineRevenue,
                'lineRevenueWithTax' => $lineRevenueWithTax,
                'lineCost'           => $lineCost !== null ? round($lineCost, 2) : null,
                'lineMargin'         => $lineMargin,
                'lineMarginPct'      => $lineMarginPct,
                'revenuePerKg'       => $revenuePerKg,
                'costPerKg'          => $costPerKg,
                'marginPerKg'        => $marginPerKg,
            ];
        }

        return $lines;
    }

    private static function buildByPallet(Order $order): array
    {
        $priceMap = [];
        foreach ($order->plannedProductDetails as $detail) {
            $priceMap[$detail->product_id] = (float) ($detail->unit_price ?? 0);
        }

        $result = [];
        foreach ($order->pallets as $pallet) {
            $totalWeightKg = 0.0;
            $totalRevenue  = 0.0;
            $totalCost     = null;
            $productNames  = [];

            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (! $box || ! $box->isAvailable) {
                    continue;
                }

                $weight         = (float) $box->net_weight;
                $totalWeightKg += $weight;
                $totalRevenue  += ($priceMap[$box->article_id] ?? 0) * $weight;

                $boxCost = $box->total_cost;
                if ($boxCost !== null) {
                    $totalCost = ($totalCost ?? 0.0) + $boxCost;
                }

                $name = $box->product?->name;
                if ($name && ! in_array($name, $productNames, true)) {
                    $productNames[] = $name;
                }
            }

            $totalRevenue  = round($totalRevenue, 2);
            $grossMargin   = $totalCost !== null ? round($totalRevenue - $totalCost, 2) : null;
            $marginPct     = ($grossMargin !== null && $totalRevenue > 0)
                ? round($grossMargin / $totalRevenue * 100, 2)
                : null;
            $revenuePerKg  = $totalWeightKg > 0 ? round($totalRevenue / $totalWeightKg, 4) : null;
            $costPerKg     = ($totalCost !== null && $totalWeightKg > 0)
                ? round($totalCost / $totalWeightKg, 4)
                : null;
            $marginPerKg   = ($grossMargin !== null && $totalWeightKg > 0)
                ? round($grossMargin / $totalWeightKg, 4)
                : null;

            $result[] = [
                'palletId'         => $pallet->id,
                'totalWeightKg'    => round($totalWeightKg, 3),
                'totalRevenue'     => $totalRevenue,
                'totalCost'        => $totalCost !== null ? round($totalCost, 2) : null,
                'grossMargin'      => $grossMargin,
                'marginPercentage' => $marginPct,
                'revenuePerKg'     => $revenuePerKg,
                'costPerKg'        => $costPerKg,
                'marginPerKg'      => $marginPerKg,
                'products'         => $productNames,
            ];
        }

        return $result;
    }
}
