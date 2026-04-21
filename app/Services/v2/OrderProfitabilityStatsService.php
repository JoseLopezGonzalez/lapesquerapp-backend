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
        $totalCost    = null;

        foreach ($orders as $order) {
            $f = self::computeOrderFinancials($order, $productIds);
            $totalRevenue += $f['revenue'];
            if ($f['cost'] !== null) {
                $totalCost = ($totalCost ?? 0.0) + $f['cost'];
            }
        }

        $grossMargin  = $totalCost !== null ? round($totalRevenue - $totalCost, 2) : null;
        $marginPct    = ($grossMargin !== null && $totalRevenue > 0)
            ? round($grossMargin / $totalRevenue * 100, 2)
            : null;

        return [
            'period'           => ['from' => $from, 'to' => $to],
            'ordersCount'      => $orders->count(),
            'totalRevenue'     => round($totalRevenue, 2),
            'totalCost'        => $totalCost !== null ? round($totalCost, 2) : null,
            'grossMargin'      => $grossMargin,
            'marginPercentage' => $marginPct,
        ];
    }

    public static function getTimeline(string $from, string $to, string $granularity, array $productIds = []): array
    {
        $orders  = self::loadOrders($from, $to, $productIds);
        $grouped = [];

        foreach ($orders as $order) {
            if (! $order->load_date) {
                continue;
            }

            $key = self::periodKey($order->load_date, $granularity);
            $f   = self::computeOrderFinancials($order, $productIds);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'periodLabel' => self::periodLabel($order->load_date, $granularity),
                    'ordersCount' => 0,
                    'revenue'     => 0.0,
                    'cost'        => null,
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
            'series'      => self::buildCompleteSeries($from, $to, $granularity, $grouped),
        ];
    }

    public static function getByProduct(string $from, string $to): array
    {
        $orders     = self::loadOrdersForProducts($from, $to);
        $byProduct  = [];

        foreach ($orders as $order) {
            $priceMap   = self::buildPriceMap($order);
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

                    $pid    = $box->article_id;
                    $weight = (float) $box->net_weight;
                    $entry  = $priceMap[$pid] ?? null;
                    $cost   = $box->total_cost;

                    if (! isset($byProduct[$pid])) {
                        $byProduct[$pid] = [
                            'product'      => $productRef[$pid] ?? $box->product,
                            'totalWeightKg'=> 0.0,
                            'totalRevenue' => 0.0,
                            'totalCost'    => null,
                            'orderIds'     => [],
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
            $revenue  = round($data['totalRevenue'], 2);
            $cost     = $data['totalCost'] !== null ? round($data['totalCost'], 2) : null;
            $margin   = $cost !== null ? round($revenue - $cost, 2) : null;
            $marginPct = ($margin !== null && $revenue > 0)
                ? round($margin / $revenue * 100, 2)
                : null;

            $p = $data['product'];

            $rows[] = [
                'product'          => ['id' => $p?->id, 'name' => $p?->name ?? '—'],
                'totalWeightKg'    => round($data['totalWeightKg'], 3),
                'totalRevenue'     => $revenue,
                'totalCost'        => $cost,
                'grossMargin'      => $margin,
                'marginPercentage' => $marginPct,
                'ordersCount'      => count($data['orderIds']),
            ];
        }

        usort($rows, fn ($a, $b) => $b['totalRevenue'] <=> $a['totalRevenue']);

        return [
            'period'   => ['from' => $from, 'to' => $to],
            'products' => $rows,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers internos
    // -------------------------------------------------------------------------

    private static function loadOrders(string $from, string $to, array $productIds = []): Collection
    {
        $query = Order::whereBetween('load_date', [
            $from . ' 00:00:00',
            $to . ' 23:59:59',
        ])->with([
            'plannedProductDetails.tax',
            'pallets.boxes.box.productionInputs',
            'pallets.boxes.box.palletBox.pallet.reception.products',
        ]);

        if (! empty($productIds)) {
            $query->whereHas('plannedProductDetails', fn ($q) => $q->whereIn('product_id', $productIds));
        }

        return $query->get();
    }

    private static function loadOrdersForProducts(string $from, string $to): Collection
    {
        return Order::whereBetween('load_date', [
            $from . ' 00:00:00',
            $to . ' 23:59:59',
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
                'price'   => (float) ($detail->unit_price ?? 0),
                'taxRate' => $detail->tax ? (float) $detail->tax->rate : 0,
            ];
        }

        return $map;
    }

    private static function computeOrderFinancials(Order $order, array $productIds = []): array
    {
        $priceMap = self::buildPriceMap($order);
        $revenue  = 0.0;
        $cost     = null;

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
                }
            }
        }

        return ['revenue' => $revenue, 'cost' => $cost];
    }

    private static function periodKey(string $date, string $granularity): string
    {
        $c = Carbon::parse($date);

        return match ($granularity) {
            'day'   => $c->format('Y-m-d'),
            'week'  => $c->format('Y-W'),
            default => $c->format('Y-m'),   // month
        };
    }

    private static function periodLabel(string $date, string $granularity): string
    {
        $c = Carbon::parse($date);

        return match ($granularity) {
            'day'   => $c->format('d/m/Y'),
            'week'  => 'Sem. ' . $c->format('W') . ' ' . $c->format('Y'),
            default => ucfirst($c->locale('es')->isoFormat('MMMM YYYY')),
        };
    }

    private static function buildCompleteSeries(
        string $from,
        string $to,
        string $granularity,
        array $grouped
    ): array {
        $series  = [];
        $current = Carbon::parse($from)->startOfDay();
        $end     = Carbon::parse($to)->endOfDay();

        while ($current->lte($end)) {
            $key  = self::periodKey($current->toDateString(), $granularity);
            $data = $grouped[$key] ?? null;

            $revenue  = $data ? round($data['revenue'], 2) : 0.0;
            $cost     = ($data && $data['cost'] !== null) ? round($data['cost'], 2) : null;
            $margin   = $cost !== null ? round($revenue - $cost, 2) : null;
            $marginPct = ($margin !== null && $revenue > 0)
                ? round($margin / $revenue * 100, 2)
                : null;

            $series[$key] = [
                'period'           => $key,
                'periodLabel'      => $data ? $data['periodLabel'] : self::periodLabel($current->toDateString(), $granularity),
                'ordersCount'      => $data['ordersCount'] ?? 0,
                'totalRevenue'     => $revenue,
                'totalCost'        => $cost,
                'grossMargin'      => $margin,
                'marginPercentage' => $marginPct,
            ];

            match ($granularity) {
                'day'  => $current->addDay(),
                'week' => $current->addWeek(),
                default => $current->addMonth(),
            };
        }

        return array_values($series);
    }
}
