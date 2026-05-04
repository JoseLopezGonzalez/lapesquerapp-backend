<?php

namespace App\Services\v2;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OrderProfitabilityStatsService
{
    // -------------------------------------------------------------------------
    // Entrada pública: tres métodos, uno por endpoint
    // -------------------------------------------------------------------------

    public static function getSummary(string $from, string $to, array $productIds = []): array
    {
        $orders = self::loadOrders($from, $to, $productIds);

        $totalRevenue = 0.0;
        $totalCost = null;
        $coveredBoxes = 0;
        $uncoveredBoxes = 0;

        foreach ($orders as $order) {
            $f = self::computeOrderFinancials($order, $productIds);
            $totalRevenue += $f['revenue'];
            $coveredBoxes += $f['coveredBoxes'];
            $uncoveredBoxes += $f['uncoveredBoxes'];
            if ($f['cost'] !== null) {
                $totalCost = ($totalCost ?? 0.0) + $f['cost'];
            }
        }

        $grossMargin = $totalCost !== null ? round($totalRevenue - $totalCost, 2) : null;
        $marginPct = ($grossMargin !== null && $totalRevenue > 0)
            ? round($grossMargin / $totalRevenue * 100, 2)
            : null;
        $totalBoxes = $coveredBoxes + $uncoveredBoxes;
        $costCoverageBoxesPct = $totalBoxes > 0
            ? round($coveredBoxes / $totalBoxes * 100, 2)
            : 0.0;

        return [
            'period' => ['from' => $from, 'to' => $to],
            'ordersCount' => $orders->count(),
            'totalRevenue' => round($totalRevenue, 2),
            'totalCost' => $totalCost !== null ? round($totalCost, 2) : null,
            'grossMargin' => $grossMargin,
            'marginPercentage' => $marginPct,
            'coveredBoxes' => $coveredBoxes,
            'uncoveredBoxes' => $uncoveredBoxes,
            'costCoverageBoxesPct' => $costCoverageBoxesPct,
        ];
    }

    public static function getSummaryExportData(string $from, string $to, array $productIds = []): array
    {
        $orders = self::loadOrders($from, $to, $productIds);
        $detailRows = [];
        $ordersSummary = [];
        $totalRevenue = 0.0;
        $knownTotalCost = null;

        foreach ($orders as $order) {
            $ordersSummary[$order->id] = [
                'order_id' => $order->id,
                'order_formatted_id' => $order->formatted_id,
                'load_date' => self::formatDate($order->load_date),
                'customer_name' => $order->customer?->name,
                'boxes_count' => 0,
                'missing_cost_boxes_count' => 0,
                'total_weight_kg' => 0.0,
                'total_revenue' => 0.0,
                'known_total_cost' => null,
                'gross_margin' => null,
                'margin_percentage' => null,
                'has_missing_costs' => 'no',
            ];

            $financials = self::computeOrderFinancials($order, $productIds);
            $totalRevenue += $financials['revenue'];
            if ($financials['cost'] !== null) {
                $knownTotalCost = ($knownTotalCost ?? 0.0) + $financials['cost'];
            }

            foreach (self::buildOrderDetailRows($order, $productIds) as $row) {
                $detailRows[] = $row;

                $ordersSummary[$order->id]['boxes_count']++;
                $ordersSummary[$order->id]['total_weight_kg'] += $row['net_weight_kg'];
                $ordersSummary[$order->id]['total_revenue'] += $row['revenue'];

                if ($row['total_cost'] !== null) {
                    $ordersSummary[$order->id]['known_total_cost'] =
                        ($ordersSummary[$order->id]['known_total_cost'] ?? 0.0) + $row['total_cost'];
                } else {
                    $ordersSummary[$order->id]['missing_cost_boxes_count']++;
                    $ordersSummary[$order->id]['has_missing_costs'] = 'yes';
                }
            }
        }

        $ordersSummary = array_map(function (array $row): array {
            $row['total_weight_kg'] = round($row['total_weight_kg'], 3);
            $row['total_revenue'] = round($row['total_revenue'], 2);
            $row['known_total_cost'] = $row['known_total_cost'] !== null
                ? round($row['known_total_cost'], 2)
                : null;
            $row['gross_margin'] = $row['known_total_cost'] !== null
                ? round($row['total_revenue'] - $row['known_total_cost'], 2)
                : null;
            $row['margin_percentage'] = ($row['gross_margin'] !== null && $row['total_revenue'] > 0)
                ? round($row['gross_margin'] / $row['total_revenue'] * 100, 2)
                : null;

            return $row;
        }, array_values($ordersSummary));

        $grossMargin = $knownTotalCost !== null ? round($totalRevenue - $knownTotalCost, 2) : null;
        $marginPct = ($grossMargin !== null && $totalRevenue > 0)
            ? round($grossMargin / $totalRevenue * 100, 2)
            : null;

        $missingCostRows = array_values(array_filter(
            $detailRows,
            fn (array $row): bool => $row['total_cost'] === null
        ));

        $missingRevenue = array_sum(array_column($missingCostRows, 'revenue'));
        $missingWeight = array_sum(array_column($missingCostRows, 'net_weight_kg'));

        return [
            'summary' => [
                ['metric' => 'date_from', 'value' => $from],
                ['metric' => 'date_to', 'value' => $to],
                ['metric' => 'orders_count', 'value' => $orders->count()],
                ['metric' => 'boxes_count', 'value' => count($detailRows)],
                ['metric' => 'boxes_with_cost_count', 'value' => count($detailRows) - count($missingCostRows)],
                ['metric' => 'boxes_missing_cost_count', 'value' => count($missingCostRows)],
                ['metric' => 'missing_cost_weight_kg', 'value' => round($missingWeight, 3)],
                ['metric' => 'missing_cost_revenue', 'value' => round($missingRevenue, 2)],
                ['metric' => 'total_revenue', 'value' => round($totalRevenue, 2)],
                ['metric' => 'known_total_cost', 'value' => $knownTotalCost !== null ? round($knownTotalCost, 2) : null],
                ['metric' => 'gross_margin', 'value' => $grossMargin],
                ['metric' => 'margin_percentage', 'value' => $marginPct],
            ],
            'detail' => $detailRows,
            'missingCosts' => $missingCostRows,
            'orders' => $ordersSummary,
        ];
    }

    public static function getTimeline(string $from, string $to, string $granularity, array $productIds = []): array
    {
        $orders = self::loadOrders($from, $to, $productIds);
        $grouped = [];

        foreach ($orders as $order) {
            if (! $order->load_date) {
                continue;
            }

            $key = self::periodKey($order->load_date, $granularity);
            $f = self::computeOrderFinancials($order, $productIds);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'periodLabel' => self::periodLabel($order->load_date, $granularity),
                    'ordersCount' => 0,
                    'revenue' => 0.0,
                    'cost' => null,
                ];
            }

            $grouped[$key]['ordersCount']++;
            $grouped[$key]['revenue'] += $f['revenue'];
            if ($f['cost'] !== null) {
                $grouped[$key]['cost'] = ($grouped[$key]['cost'] ?? 0.0) + $f['cost'];
            }
        }

        return [
            'granularity' => $granularity,
            'series' => self::buildCompleteSeries($from, $to, $granularity, $grouped),
        ];
    }

    public static function getByProduct(string $from, string $to): array
    {
        $orders = self::loadOrdersForProducts($from, $to);
        $byProduct = [];

        foreach ($orders as $order) {
            $priceMap = self::buildPriceMap($order);
            $productRef = [];
            foreach ($order->plannedProductDetails as $d) {
                $productRef[$d->product_id] = $d->product;
            }

            foreach ($order->pallets as $pallet) {
                foreach ($pallet->boxes as $palletBox) {
                    $box = $palletBox->box;
                    if (! $box || ! $box->isAvailable) {
                        continue;
                    }

                    $pid = $box->article_id;
                    $weight = (float) $box->net_weight;
                    $entry = $priceMap[$pid] ?? null;
                    $cost = $box->total_cost;

                    if (! isset($byProduct[$pid])) {
                        $byProduct[$pid] = [
                            'product' => $productRef[$pid] ?? $box->product,
                            'totalWeightKg' => 0.0,
                            'totalRevenue' => 0.0,
                            'totalCost' => null,
                            'orderIds' => [],
                        ];
                    }

                    $byProduct[$pid]['totalWeightKg'] += $weight;
                    if ($entry) {
                        $byProduct[$pid]['totalRevenue'] += $entry['price'] * $weight;
                    }
                    if ($cost !== null) {
                        $byProduct[$pid]['totalCost'] = ($byProduct[$pid]['totalCost'] ?? 0.0) + $cost;
                    }
                    $byProduct[$pid]['orderIds'][$order->id] = true;
                }
            }
        }

        $rows = [];
        foreach ($byProduct as $data) {
            $weightKg = round($data['totalWeightKg'], 3);
            $revenue = round($data['totalRevenue'], 2);
            $cost = $data['totalCost'] !== null ? round($data['totalCost'], 2) : null;
            $margin = $cost !== null ? round($revenue - $cost, 2) : null;
            $marginPct = ($margin !== null && $revenue > 0)
                ? round($margin / $revenue * 100, 2)
                : null;
            $revenuePerKg = $weightKg > 0 ? round($revenue / $weightKg, 4) : null;
            $costPerKg = ($cost !== null && $weightKg > 0) ? round($cost / $weightKg, 4) : null;
            $marginPerKg = ($margin !== null && $weightKg > 0) ? round($margin / $weightKg, 4) : null;

            $p = $data['product'];

            $rows[] = [
                'product' => ['id' => $p?->id, 'name' => $p?->name ?? '—'],
                'totalWeightKg' => $weightKg,
                'totalRevenue' => $revenue,
                'totalCost' => $cost,
                'grossMargin' => $margin,
                'marginPercentage' => $marginPct,
                'revenuePerKg' => $revenuePerKg,
                'costPerKg' => $costPerKg,
                'marginPerKg' => $marginPerKg,
                'ordersCount' => count($data['orderIds']),
            ];
        }

        usort($rows, fn ($a, $b) => $b['totalRevenue'] <=> $a['totalRevenue']);

        return [
            'period' => ['from' => $from, 'to' => $to],
            'products' => $rows,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers internos
    // -------------------------------------------------------------------------

    private static function loadOrders(string $from, string $to, array $productIds = []): Collection
    {
        $query = Order::query()
            ->whereIn('status', [Order::STATUS_FINISHED, Order::STATUS_INCIDENT])
            ->whereBetween('load_date', [
                $from.' 00:00:00',
                $to.' 23:59:59',
            ])->with([
                'customer',
                'plannedProductDetails.tax',
                'plannedProductDetails.product',
                'pallets.boxes.box.productionInputs',
                'pallets.boxes.box.product',
                'pallets.boxes.box.palletBox.pallet.reception.products',
            ]);

        if (! empty($productIds)) {
            $query->whereHas('plannedProductDetails', fn ($q) => $q->whereIn('product_id', $productIds));
        }

        return $query->get();
    }

    private static function buildOrderDetailRows(Order $order, array $productIds = []): array
    {
        $rows = [];
        $priceMap = self::buildPriceMap($order);

        foreach ($order->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (! $box || ! $box->isAvailable) {
                    continue;
                }
                if (! empty($productIds) && ! in_array($box->article_id, $productIds, true)) {
                    continue;
                }

                $weight = (float) $box->net_weight;
                $unitPrice = $priceMap[$box->article_id]['price'] ?? null;
                $revenue = $unitPrice !== null ? $unitPrice * $weight : 0.0;
                $totalCost = $box->total_cost;
                $costPerKg = ($totalCost !== null && $weight > 0) ? $totalCost / $weight : null;
                $manualCostPerKg = $box->manual_cost_per_kg !== null ? (float) $box->manual_cost_per_kg : null;
                $grossMargin = $totalCost !== null ? $revenue - $totalCost : null;
                $marginPercentage = ($grossMargin !== null && $revenue > 0)
                    ? $grossMargin / $revenue * 100
                    : null;

                $rows[] = [
                    'order_id' => $order->id,
                    'order_formatted_id' => $order->formatted_id,
                    'load_date' => self::formatDate($order->load_date),
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer?->name,
                    'pallet_id' => $pallet->id,
                    'box_id' => $box->id,
                    'product_id' => $box->article_id,
                    'product_name' => $box->product?->name ?? ($priceMap[$box->article_id]['productName'] ?? null),
                    'lot' => $box->lot,
                    'net_weight_kg' => round($weight, 3),
                    'unit_price' => $unitPrice,
                    'revenue' => round($revenue, 2),
                    'cost_per_kg' => $costPerKg !== null ? round($costPerKg, 4) : null,
                    'total_cost' => $totalCost !== null ? round($totalCost, 2) : null,
                    'gross_margin' => $grossMargin !== null ? round($grossMargin, 2) : null,
                    'margin_percentage' => $marginPercentage !== null ? round($marginPercentage, 2) : null,
                    'cost_status' => $totalCost !== null ? 'with_cost' : 'missing_cost',
                    'cost_source' => self::resolveCostSource($box, $totalCost),
                    'is_available' => 'yes',
                    'included_in_summary' => 'yes',
                    'exclusion_reason' => null,
                    'manual_cost_per_kg' => $manualCostPerKg !== null ? round($manualCostPerKg, 4) : null,
                    'manual_total_cost' => $manualCostPerKg !== null ? round($manualCostPerKg * $weight, 2) : null,
                    'notes' => null,
                ];
            }
        }

        return $rows;
    }

    private static function loadOrdersForProducts(string $from, string $to): Collection
    {
        return Order::query()
            ->whereIn('status', [Order::STATUS_FINISHED, Order::STATUS_INCIDENT])
            ->whereBetween('load_date', [
                $from.' 00:00:00',
                $to.' 23:59:59',
            ])->with([
                'plannedProductDetails.tax',
                'plannedProductDetails.product',
                'pallets.boxes.box.productionInputs',
                'pallets.boxes.box.product',
                'pallets.boxes.box.palletBox.pallet.reception.products',
            ])->get();
    }

    private static function buildPriceMap(Order $order): array
    {
        $map = [];
        foreach ($order->plannedProductDetails as $detail) {
            $map[$detail->product_id] = [
                'price' => (float) ($detail->unit_price ?? 0),
                'taxRate' => $detail->tax ? (float) $detail->tax->rate : 0,
                'productName' => $detail->product?->name,
            ];
        }

        return $map;
    }

    private static function resolveCostSource($box, ?float $totalCost): string
    {
        if ($totalCost === null) {
            return 'missing';
        }

        $pallet = $box->pallet;
        $reception = $pallet?->reception;
        if ($reception) {
            $hasReceptionPrice = $reception->products
                ->first(fn ($product) => (int) $product->product_id === (int) $box->article_id && $product->lot === $box->lot)
                ?->price !== null;

            if ($hasReceptionPrice) {
                return 'reception';
            }
        }

        if ($box->traceable_cost_per_kg !== null) {
            return 'production';
        }

        return $box->manual_cost_per_kg !== null ? 'manual' : 'missing';
    }

    private static function formatDate($date): ?string
    {
        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    private static function computeOrderFinancials(Order $order, array $productIds = []): array
    {
        $priceMap = self::buildPriceMap($order);
        $revenue = 0.0;
        $cost = null;
        $coveredBoxes = 0;
        $uncoveredBoxes = 0;

        foreach ($order->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (! $box || ! $box->isAvailable) {
                    continue;
                }
                if (! empty($productIds) && ! in_array($box->article_id, $productIds, true)) {
                    continue;
                }

                $entry = $priceMap[$box->article_id] ?? null;
                if ($entry) {
                    $revenue += $entry['price'] * (float) $box->net_weight;
                }

                $boxCost = $box->total_cost;
                if ($boxCost !== null) {
                    $cost = ($cost ?? 0.0) + $boxCost;
                    $coveredBoxes++;
                } else {
                    $uncoveredBoxes++;
                }
            }
        }

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'coveredBoxes' => $coveredBoxes,
            'uncoveredBoxes' => $uncoveredBoxes,
        ];
    }

    private static function periodKey(string $date, string $granularity): string
    {
        $c = Carbon::parse($date);

        return match ($granularity) {
            'day' => $c->format('Y-m-d'),
            'week' => $c->format('Y-W'),
            default => $c->format('Y-m'),   // month
        };
    }

    private static function periodLabel(string $date, string $granularity): string
    {
        $c = Carbon::parse($date);

        return match ($granularity) {
            'day' => $c->format('d/m/Y'),
            'week' => 'Sem. '.$c->format('W').' '.$c->format('Y'),
            default => ucfirst($c->locale('es')->isoFormat('MMMM YYYY')),
        };
    }

    private static function buildCompleteSeries(
        string $from,
        string $to,
        string $granularity,
        array $grouped
    ): array {
        $series = [];
        $current = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        while ($current->lte($end)) {
            $key = self::periodKey($current->toDateString(), $granularity);
            $data = $grouped[$key] ?? null;

            $revenue = $data ? round($data['revenue'], 2) : 0.0;
            $cost = ($data && $data['cost'] !== null) ? round($data['cost'], 2) : null;
            $margin = $cost !== null ? round($revenue - $cost, 2) : null;
            $marginPct = ($margin !== null && $revenue > 0)
                ? round($margin / $revenue * 100, 2)
                : null;

            $series[$key] = [
                'period' => $key,
                'periodLabel' => $data ? $data['periodLabel'] : self::periodLabel($current->toDateString(), $granularity),
                'ordersCount' => $data['ordersCount'] ?? 0,
                'totalRevenue' => $revenue,
                'totalCost' => $cost,
                'grossMargin' => $margin,
                'marginPercentage' => $marginPct,
            ];

            match ($granularity) {
                'day' => $current->addDay(),
                'week' => $current->addWeek(),
                default => $current->addMonth(),
            };
        }

        return array_values($series);
    }
}
