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
        // Usar la misma lógica que totalAmountStats y totalNetWeightStats: usar load_date
        // Para quantity: usar la misma estructura que calculateTotalNetWeight (boxes.net_weight con articles)
        // Para amount: usar order_planned_product_details (igual que calculateAmountDetails)
        
        if ($valueType === 'quantity') {
            // Para quantity, usar la misma estructura que calculateTotalNetWeight
            // Nota: joinBoxesAndArticles hace join con articles, pero para los filtros usamos products
            $query = Order::query()
                ->join('pallets', 'pallets.order_id', '=', 'orders.id')
                ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
                ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
                ->join('articles', 'articles.id', '=', 'boxes.article_id')
                ->join('products', 'products.id', '=', 'articles.id') // products.id = articles.id (relación 1:1)
                ->leftJoin('product_families', 'products.family_id', '=', 'product_families.id')
                ->leftJoin('product_categories', 'product_families.category_id', '=', 'product_categories.id')
                ->whereBetween('orders.load_date', [$dateFrom, $dateTo]);

            // Aplicar filtros (usar products.species_id ya que articles no tiene species_id directamente)
            // Esto es consistente porque products.id = articles.id (relación 1:1)
            if ($speciesId) {
                $query->where('products.species_id', $speciesId);
            }
            if ($familyId) {
                $query->where('products.family_id', $familyId);
            }
            if ($categoryId) {
                $query->where('product_families.category_id', $categoryId);
            }

            $dateField = 'orders.load_date';
            $valueField = 'SUM(boxes.net_weight)';
        } else {
            // Para amount, usar order_planned_product_details (igual que calculateAmountDetails)
            $query = Order::query()
                ->join('order_planned_product_details', 'orders.id', '=', 'order_planned_product_details.order_id')
                ->join('products', 'order_planned_product_details.product_id', '=', 'products.id')
                ->leftJoin('taxes', 'order_planned_product_details.tax_id', '=', 'taxes.id')
                ->leftJoin('product_families', 'products.family_id', '=', 'product_families.id')
                ->leftJoin('product_categories', 'product_families.category_id', '=', 'product_categories.id')
                ->whereBetween('orders.load_date', [$dateFrom, $dateTo]);

            // Aplicar filtros
            if ($speciesId) {
                $query->where('products.species_id', $speciesId);
            }
            if ($familyId) {
                $query->where('products.family_id', $familyId);
            }
            if ($categoryId) {
                $query->where('product_families.category_id', $categoryId);
            }

            $dateField = 'orders.load_date';
            $valueField = 'SUM(order_planned_product_details.unit_price * order_planned_product_details.quantity * (1 + COALESCE(taxes.rate, 0) / 100))';
        }

        // Agrupar por fecha según groupBy
        $dateFormat = match ($groupBy) {
            'week' => "DATE_FORMAT({$dateField}, '%Y-%u')", // Año-semana
            'month' => "DATE_FORMAT({$dateField}, '%Y-%m')", // Año-mes
            'day' => "DATE_FORMAT({$dateField}, '%Y-%m-%d')", // Año-mes-día
            default => "DATE_FORMAT({$dateField}, '%Y-%m-%d')",
        };

        $dateAlias = match ($groupBy) {
            'week' => "DATE_FORMAT({$dateField}, '%Y-W%u')",
            'month' => "DATE_FORMAT({$dateField}, '%Y-%m')",
            'day' => "DATE_FORMAT({$dateField}, '%Y-%m-%d')",
            default => "DATE_FORMAT({$dateField}, '%Y-%m-%d')",
        };

        $results = $query->selectRaw("
            {$dateAlias} as date,
            {$valueField} as value
        ")
        ->groupByRaw($dateFormat)
        ->orderByRaw($dateFormat)
        ->get();

        return $results->map(fn($item) => [
            'date' => $item->date,
            'value' => round($item->value, 2),
        ])->values();
    }












}


