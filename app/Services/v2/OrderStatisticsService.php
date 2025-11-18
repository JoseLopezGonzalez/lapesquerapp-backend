<?php

namespace App\Services\v2;

use App\Models\Order;
use Carbon\Carbon;

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

    /**
     * Obtiene datos de ventas agrupados por período (día, semana o mes)
     * con filtros opcionales por especie, familia o categoría.
     * 
     * @param string $dateFrom Fecha de inicio (formato: Y-m-d H:i:s)
     * @param string $dateTo Fecha de fin (formato: Y-m-d H:i:s)
     * @param string $valueType Tipo de valor: 'amount' o 'quantity'
     * @param string $groupBy Agrupación: 'day', 'week' o 'month'
     * @param int|null $speciesId ID de especie para filtrar
     * @param int|null $familyId ID de familia para filtrar
     * @param int|null $categoryId ID de categoría para filtrar
     * @return \Illuminate\Support\Collection
     */
    public static function getSalesChartData(
        string $dateFrom,
        string $dateTo,
        string $valueType,
        string $groupBy,
        ?int $speciesId = null,
        ?int $familyId = null,
        ?int $categoryId = null
    ): \Illuminate\Support\Collection {
        $orders = Order::with(
            'pallets.boxes.box.product.species',
            'pallets.boxes.box.product.family',
            'pallets.boxes.box.product.family.category',
            'plannedProductDetails.product',
            'plannedProductDetails.tax'
        )
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->get();

        $grouped = [];

        foreach ($orders as $order) {
            if (!$order->entry_date) {
                continue;
            }

            $date = Carbon::parse($order->entry_date);

            switch ($groupBy) {
                case 'week':
                    $entryDate = $date->startOfWeek()->format('Y-\WW'); // Ej: 2025-W27
                    break;
                case 'month':
                    $entryDate = $date->format('Y-m'); // Ej: 2025-07
                    break;
                case 'day':
                default:
                    $entryDate = $date->format('Y-m-d'); // Ej: 2025-07-02
                    break;
            }

            // Calcular valores solo de las cajas que cumplen los filtros
            $filteredAmount = 0;
            $filteredQuantity = 0;

            foreach ($order->pallets as $pallet) {
                foreach ($pallet->boxes as $box) {
                    $product = $box->box->product;
                    
                    if (!$product) {
                        continue;
                    }

                    // Verificar filtros
                    $matchesSpecies = !$speciesId || ($product->species && $product->species->id == $speciesId);
                    $matchesFamily = !$familyId || ($product->family && $product->family->id == $familyId);
                    $matchesCategory = !$categoryId || (
                        $product->family && 
                        $product->family->category && 
                        $product->family->category->id == $categoryId
                    );

                    // Si no cumple todos los filtros, saltar esta caja
                    if (!$matchesSpecies || !$matchesFamily || !$matchesCategory) {
                        continue;
                    }

                    // Sumar cantidad (peso neto)
                    $filteredQuantity += floatval($box->netWeight);

                    // Calcular monto
                    $plannedProductDetail = $order->plannedProductDetails->firstWhere('product_id', $product->id);
                    if ($plannedProductDetail) {
                        $subtotal = $plannedProductDetail->unit_price * $box->netWeight;
                        $taxRate = $plannedProductDetail->tax ? $plannedProductDetail->tax->rate : 0;
                        $total = $subtotal + ($subtotal * $taxRate / 100);
                        $filteredAmount += floatval($total);
                    }
                }
            }

            // Solo agregar si hay datos que cumplen los filtros
            if ($filteredQuantity > 0 || $filteredAmount > 0) {
                if (!isset($grouped[$entryDate])) {
                    $grouped[$entryDate] = [
                        'date' => $entryDate,
                        'amount' => 0,
                        'quantity' => 0,
                    ];
                }

                $grouped[$entryDate]['amount'] += $filteredAmount;
                $grouped[$entryDate]['quantity'] += $filteredQuantity;
            }
        }

        return collect($grouped)
            ->sortKeys()
            ->map(fn($item) => [
                'date' => $item['date'],
                'value' => round($item[$valueType], 2),
            ])
            ->values();
    }












}


