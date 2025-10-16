<?php

namespace App\Services\v2;

use App\Models\Order;

class OrderStatisticsService
{

    public static function prepareDateRangeAndPrevious(string $dateFrom, string $dateTo): array
    {
        $from = $dateFrom . ' 00:00:00';
        $to = $dateTo . ' 23:59:59';

        $fromPrev = date('Y-m-d H:i:s', strtotime($from . ' -1 year'));
        $toPrev = date('Y-m-d H:i:s', strtotime($to . ' -1 year'));

        return [
            'from' => $from,
            'to' => $to,
            'fromPrev' => $fromPrev,
            'toPrev' => $toPrev,
        ];
    }

    public static function calculateTotalNetWeight(string $from, string $to, ?int $speciesId = null): float
    {
        $query = Order::query()
            ->joinBoxesAndArticles()
            ->whereBoxArticleSpecies($speciesId)
            ->betweenLoadDates($from, $to);

        return Order::executeNetWeightSum($query);
    }


    public static function compareTotals(float $current, float $previous): ?float
    {
        if ($previous == 0) {
            return null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    public static function getNetWeightStatsComparedToLastYear(string $dateFrom, string $dateTo, ?int $speciesId = null): array
    {
        $range = self::prepareDateRangeAndPrevious($dateFrom, $dateTo);

        $totalCurrent = self::calculateTotalNetWeight($range['from'], $range['to'], $speciesId);
        $totalPrevious = self::calculateTotalNetWeight($range['fromPrev'], $range['toPrev'], $speciesId);

        return [
            'value' => round($totalCurrent, 2),
            'comparisonValue' => round($totalPrevious, 2),
            'percentageChange' => self::compareTotals($totalCurrent, $totalPrevious) !== null
                ? round(self::compareTotals($totalCurrent, $totalPrevious), 2)
                : null,
            'range' => [
                'from' => $range['from'],
                'to' => $range['to'],
                'fromPrev' => $range['fromPrev'],
                'toPrev' => $range['toPrev'],
            ]
        ];
    }


    /* public static function calculateTotalAmount(string $from, string $to, ?int $speciesId = null): float
    {
        return Order::query()
            ->withPlannedProductDetails()
            ->wherePlannedProductSpecies($speciesId)
            ->betweenLoadDates($from, $to)
            ->get()
            ->sum(fn($order) => $order->totalAmount);
    }

    public static function calculateSubtotalAmount(string $from, string $to, ?int $speciesId = null): float
    {
        return Order::query()
            ->withPlannedProductDetails()
            ->wherePlannedProductSpecies($speciesId)
            ->betweenLoadDates($from, $to)
            ->get()
            ->sum(fn($order) => $order->subtotalAmount);
    } */

    public static function calculateAmountDetails(string $from, string $to, ?int $speciesId = null): array
    {
        // Optimización: usar consultas SQL directas en lugar de cargar todos los datos en memoria
        $query = Order::query()
            ->join('order_planned_product_details', 'orders.id', '=', 'order_planned_product_details.order_id')
            ->join('products', 'order_planned_product_details.product_id', '=', 'products.id')
            ->leftJoin('taxes', 'order_planned_product_details.tax_id', '=', 'taxes.id')
            ->whereBetween('orders.load_date', [$from, $to]);

        if ($speciesId) {
            $query->where('products.species_id', $speciesId);
        }

        $result = $query->selectRaw('
            SUM(order_planned_product_details.unit_price * order_planned_product_details.quantity) as subtotal,
            SUM(order_planned_product_details.unit_price * order_planned_product_details.quantity * (1 + COALESCE(taxes.rate, 0) / 100)) as total
        ')
        ->first();

        $subtotal = $result->subtotal ?? 0;
        $total = $result->total ?? 0;
        $tax = $total - $subtotal;

        return [
            'total' => $total,
            'subtotal' => $subtotal,
            'tax' => $tax,
        ];
    }



    public static function getAmountStatsComparedToLastYear(string $dateFrom, string $dateTo, ?int $speciesId = null): array
    {
        $range = self::prepareDateRangeAndPrevious($dateFrom, $dateTo);

        $current = self::calculateAmountDetails($range['from'], $range['to'], $speciesId);
        $previous = self::calculateAmountDetails($range['fromPrev'], $range['toPrev'], $speciesId);

        return [
            'value' => round($current['total'], 2),
            'subtotal' => round($current['subtotal'], 2),
            'tax' => round($current['tax'], 2),

            'comparisonValue' => round($previous['total'], 2),
            'comparisonSubtotal' => round($previous['subtotal'], 2),
            'comparisonTax' => round($previous['tax'], 2),

            'percentageChange' => self::compareTotals($current['total'], $previous['total']) !== null
                ? round(self::compareTotals($current['total'], $previous['total']), 2)
                : null,

            'range' => $range,
        ];
    }


    public static function getOrderRankingStats(string $groupBy, string $valueType, string $dateFrom, string $dateTo, ?int $speciesId = null): \Illuminate\Support\Collection
    {
        // Optimización: usar consultas SQL directas en lugar de cargar todos los datos en memoria
        $query = Order::query()
            ->join('order_planned_product_details', 'orders.id', '=', 'order_planned_product_details.order_id')
            ->join('products', 'order_planned_product_details.product_id', '=', 'products.id')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->leftJoin('countries', 'customers.country_id', '=', 'countries.id')
            ->leftJoin('taxes', 'order_planned_product_details.tax_id', '=', 'taxes.id')
            ->whereBetween('orders.load_date', [$dateFrom, $dateTo]);

        if ($speciesId) {
            $query->where('products.species_id', $speciesId);
        }

        $groupByField = match ($groupBy) {
            'client' => 'customers.name',
            'country' => 'countries.name',
            'product' => 'products.name',
        };

        $valueField = match ($valueType) {
            'totalAmount' => 'SUM(order_planned_product_details.unit_price * order_planned_product_details.quantity * (1 + COALESCE(taxes.rate, 0) / 100))',
            'totalQuantity' => 'SUM(order_planned_product_details.quantity)',
        };

        $results = $query->selectRaw("
            {$groupByField} as name,
            {$valueField} as value
        ")
        ->groupBy($groupByField)
        ->orderByDesc('value')
        ->get();

        return $results->map(fn($item) => [
            'name' => $item->name ?? 'Sin nombre',
            'value' => round($item->value, 2),
        ]);
    }












}


