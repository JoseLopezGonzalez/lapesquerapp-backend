<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\Order;
use App\Models\Pallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderStatisticsService
{
    /** Pedidos contabilizados en estadísticas sobre ventas cerradas (reexporta modelo Order). */
    private static function closedSalesOrderStatuses(): array
    {
        return Order::closedSalesReportingStatuses();
    }

    /**
     * Kg expedidos por pedido/producto desde cajas en palets, excluyendo cajas consumidas en producción
     * (equivalente a isAvailable en OrderProfitabilityStatsService).
     */
    private static function expeditedKgPerOrderProductSubquery()
    {
        return DB::table('pallets')
            ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
            ->whereNull('production_inputs.id')
            ->groupBy('pallets.order_id', 'boxes.article_id')
            ->select('pallets.order_id', DB::raw('boxes.article_id as product_id'), DB::raw('SUM(boxes.net_weight) as expedited_kg'));
    }

    private static function plannedQtySumPerOrderProductSubquery()
    {
        return DB::table('order_planned_product_details')
            ->select('order_id', 'product_id', DB::raw('SUM(quantity) as sum_planned_qty'))
            ->groupBy('order_id', 'product_id');
    }

    private static function plannedLineCountPerOrderProductSubquery()
    {
        return DB::table('order_planned_product_details')
            ->select('order_id', 'product_id', DB::raw('COUNT(*) as line_count'))
            ->groupBy('order_id', 'product_id');
    }

    /**
     * Reparte kg reales entre varias líneas del mismo pedido/producto en proporción a quantity planificada;
     * si todas las quantities son 0, reparto equitativo entre líneas (evita doble conteo).
     */
    private static function sqlAllocatedKgExpression(): string
    {
        return '(COALESCE(exp.expedited_kg, 0) * (CASE WHEN COALESCE(qty_sum.sum_planned_qty, 0) > 0 '
            .'THEN order_planned_product_details.quantity / qty_sum.sum_planned_qty '
            .'WHEN COALESCE(line_cnt.line_count, 0) > 0 THEN 1.0 / line_cnt.line_count '
            .'ELSE 0 END))';
    }

    private static function applyExpeditedKgAllocationJoins(Builder $query): void
    {
        $query
            ->leftJoinSub(self::expeditedKgPerOrderProductSubquery(), 'exp', function ($join) {
                $join->on('exp.order_id', '=', 'orders.id')
                    ->on('exp.product_id', '=', 'order_planned_product_details.product_id');
            })
            ->leftJoinSub(self::plannedQtySumPerOrderProductSubquery(), 'qty_sum', function ($join) {
                $join->on('qty_sum.order_id', '=', 'orders.id')
                    ->on('qty_sum.product_id', '=', 'order_planned_product_details.product_id');
            })
            ->leftJoinSub(self::plannedLineCountPerOrderProductSubquery(), 'line_cnt', function ($join) {
                $join->on('line_cnt.order_id', '=', 'orders.id')
                    ->on('line_cnt.product_id', '=', 'order_planned_product_details.product_id');
            });
    }

    private static function applyClosedSalesOrdersFilter(Builder $query): void
    {
        $query->whereIn('orders.status', self::closedSalesOrderStatuses());
    }

    public static function prepareDateRangeAndPrevious(string $dateFrom, string $dateTo): array
    {
        $from = $dateFrom.' 00:00:00';
        $to = $dateTo.' 23:59:59';

        $fromPrev = date('Y-m-d H:i:s', strtotime($from.' -1 year'));
        $toPrev = date('Y-m-d H:i:s', strtotime($to.' -1 year'));

        return [
            'from' => $from,
            'to' => $to,
            'fromPrev' => $fromPrev,
            'toPrev' => $toPrev,
        ];
    }

    public static function calculateTotalNetWeight(string $from, string $to, ?int $speciesId = null, ?User $user = null): float
    {
        $user = $user ?? auth()->user();
        $query = Order::query()
            ->joinBoxesAndArticles()
            ->whereBoxArticleSpecies($speciesId)
            ->betweenLoadDates($from, $to)
            ->whereIn('orders.status', Order::closedSalesReportingStatuses());

        if ($user && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('orders.salesperson_id', $user->salesperson->id);
        }

        return Order::executeNetWeightSum($query);
    }

    public static function compareTotals(float $current, float $previous): ?float
    {
        if ($previous == 0) {
            return null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    public static function getNetWeightStatsComparedToLastYear(string $dateFrom, string $dateTo, ?int $speciesId = null, ?User $user = null): array
    {
        $range = self::prepareDateRangeAndPrevious($dateFrom, $dateTo);

        $totalCurrent = self::calculateTotalNetWeight($range['from'], $range['to'], $speciesId, $user);
        $totalPrevious = self::calculateTotalNetWeight($range['fromPrev'], $range['toPrev'], $speciesId, $user);

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
            ],
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

    public static function calculateAmountDetails(string $from, string $to, ?int $speciesId = null, ?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $allocated = self::sqlAllocatedKgExpression();
        // Precio unitario e IVA de la previsión; kg efectivos desde cajas en palets del pedido.
        $query = Order::query()
            ->join('order_planned_product_details', 'orders.id', '=', 'order_planned_product_details.order_id')
            ->join('products', 'order_planned_product_details.product_id', '=', 'products.id')
            ->leftJoin('taxes', 'order_planned_product_details.tax_id', '=', 'taxes.id')
            ->tap(fn (Builder $q) => self::applyExpeditedKgAllocationJoins($q))
            ->whereBetween('orders.load_date', [$from, $to]);
        self::applyClosedSalesOrdersFilter($query);

        if ($speciesId) {
            $query->where('products.species_id', $speciesId);
        }

        if ($user && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('orders.salesperson_id', $user->salesperson->id);
        }

        $result = $query->selectRaw("
            SUM(order_planned_product_details.unit_price * {$allocated}) as subtotal,
            SUM(order_planned_product_details.unit_price * {$allocated} * (1 + COALESCE(taxes.rate, 0) / 100)) as total
        ")
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

    public static function getAmountStatsComparedToLastYear(string $dateFrom, string $dateTo, ?int $speciesId = null, ?User $user = null): array
    {
        $range = self::prepareDateRangeAndPrevious($dateFrom, $dateTo);

        $current = self::calculateAmountDetails($range['from'], $range['to'], $speciesId, $user);
        $previous = self::calculateAmountDetails($range['fromPrev'], $range['toPrev'], $speciesId, $user);

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

    public static function getOrderRankingStats(string $groupBy, string $valueType, string $dateFrom, string $dateTo, ?int $speciesId = null, ?User $user = null): \Illuminate\Support\Collection
    {
        $user = $user ?? auth()->user();
        $allocated = self::sqlAllocatedKgExpression();
        $query = Order::query()
            ->join('order_planned_product_details', 'orders.id', '=', 'order_planned_product_details.order_id')
            ->join('products', 'order_planned_product_details.product_id', '=', 'products.id')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->leftJoin('countries', 'customers.country_id', '=', 'countries.id')
            ->leftJoin('taxes', 'order_planned_product_details.tax_id', '=', 'taxes.id')
            ->tap(fn (Builder $q) => self::applyExpeditedKgAllocationJoins($q))
            ->whereBetween('orders.load_date', [$dateFrom, $dateTo]);
        self::applyClosedSalesOrdersFilter($query);

        if ($speciesId) {
            $query->where('products.species_id', $speciesId);
        }

        if ($user && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('orders.salesperson_id', $user->salesperson->id);
        }

        $groupByField = match ($groupBy) {
            'client' => 'customers.name',
            'country' => 'countries.name',
            'product' => 'products.name',
        };

        $valueField = match ($valueType) {
            'totalAmount' => "SUM(order_planned_product_details.unit_price * {$allocated} * (1 + COALESCE(taxes.rate, 0) / 100))",
            'totalQuantity' => 'SUM(order_planned_product_details.quantity)',
        };

        $results = $query->selectRaw("
            {$groupByField} as name,
            {$valueField} as value
        ")
            ->groupBy($groupByField)
            ->orderByDesc('value')
            ->get();

        return $results->map(fn ($item) => [
            'name' => $item->name ?? 'Sin nombre',
            'value' => round($item->value, 2),
        ]);
    }

    /**
     * Obtiene datos de ventas agrupados por período (día, semana o mes).
     * con filtros opcionales por especie, familia o categoría.
     *
     * @param  string  $dateFrom  Fecha de inicio (formato: Y-m-d H:i:s)
     * @param  string  $dateTo  Fecha de fin (formato: Y-m-d H:i:s)
     * @param  string  $valueType  Tipo de valor: 'amount' o 'quantity'
     * @param  string  $groupBy  Agrupación: 'day', 'week' o 'month'
     * @param  int|null  $speciesId  ID de especie para filtrar
     * @param  int|null  $familyId  ID de familia para filtrar
     * @param  int|null  $categoryId  ID de categoría para filtrar
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
        // Usar load_date coherente con el resto de estadísticas de pedidos.
        $allocatedForChart = self::sqlAllocatedKgExpression();

        if ($valueType === 'quantity') {
            $query = Order::query()
                ->join('pallets', 'pallets.order_id', '=', 'orders.id')
                ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
                ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
                ->join('products', 'products.id', '=', 'boxes.article_id')
                ->leftJoin('product_families', 'products.family_id', '=', 'product_families.id')
                ->leftJoin('product_categories', 'product_families.category_id', '=', 'product_categories.id')
                ->whereBetween('orders.load_date', [$dateFrom, $dateTo]);
            self::applyClosedSalesOrdersFilter($query);

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
            // Precio de previsión × kg desde cajas (subtotal sin IVA, igual criterio que calculateAmountDetails)
            $query = Order::query()
                ->join('order_planned_product_details', 'orders.id', '=', 'order_planned_product_details.order_id')
                ->join('products', 'order_planned_product_details.product_id', '=', 'products.id')
                ->leftJoin('product_families', 'products.family_id', '=', 'product_families.id')
                ->leftJoin('product_categories', 'product_families.category_id', '=', 'product_categories.id')
                ->tap(fn (Builder $q) => self::applyExpeditedKgAllocationJoins($q))
                ->whereBetween('orders.load_date', [$dateFrom, $dateTo]);
            self::applyClosedSalesOrdersFilter($query);

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
            $valueField = "SUM(order_planned_product_details.unit_price * {$allocatedForChart})";
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

        return $results->map(fn ($item) => [
            'date' => $item->date,
            'value' => round($item->value, 2),
        ])->values();
    }

    /**
     * Ventas por comercial: peso neto de cajas disponibles agrupado por salesperson en rango load_date del pedido.
     * Sustituye la consulta raw de OrderController::salesBySalesperson usando Eloquent (conexión tenant vía Order).
     *
     * @param  string  $dateFrom  Fecha inicio (Y-m-d)
     * @param  string  $dateTo  Fecha fin (Y-m-d)
     * @param  User|null  $user  Usuario actual (para filtrar por salesperson si es comercial)
     * @return Collection<int, array{name: string, quantity: float}>
     */
    public static function getSalesBySalesperson(string $dateFrom, string $dateTo, ?User $user = null): Collection
    {
        $user = $user ?? auth()->user();
        $dateFrom = $dateFrom.' 00:00:00';
        $dateTo = $dateTo.' 23:59:59';

        $query = Order::query()
            ->join('pallets', 'pallets.order_id', '=', 'orders.id')
            ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
            ->leftJoin('salespeople', 'salespeople.id', '=', 'orders.salesperson_id')
            ->whereBetween('orders.load_date', [$dateFrom, $dateTo])
            ->whereIn('orders.status', Order::closedSalesReportingStatuses())
            ->whereNull('production_inputs.id')
            ->whereIn('pallets.status', [
                Pallet::STATE_REGISTERED,
                Pallet::STATE_STORED,
                Pallet::STATE_SHIPPED,
            ]);

        if ($user && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('orders.salesperson_id', $user->salesperson->id);
        }

        $results = $query
            ->selectRaw('COALESCE(salespeople.name, "Sin comercial") as name, SUM(boxes.net_weight) as quantity')
            ->groupBy('salespeople.id', 'salespeople.name')
            ->get();

        return $results->map(fn ($item) => [
            'name' => $item->name,
            'quantity' => round((float) $item->quantity, 2),
        ])->values();
    }

    /**
     * Datos para gráfico por transportista: peso neto de cajas disponibles agrupado por transport en rango load_date.
     * Sustituye la consulta raw de OrderController::transportChartData usando Eloquent (conexión tenant vía Order).
     *
     * @param  string  $dateFrom  Fecha inicio (Y-m-d)
     * @param  string  $dateTo  Fecha fin (Y-m-d)
     * @param  User|null  $user  Usuario actual (para filtrar por salesperson si es comercial)
     * @return Collection<int, array{name: string, netWeight: float}>
     */
    public static function getTransportChartData(string $dateFrom, string $dateTo, ?User $user = null): Collection
    {
        $user = $user ?? auth()->user();
        $from = $dateFrom.' 00:00:00';
        $to = $dateTo.' 23:59:59';

        $query = Order::query()
            ->join('transports', 'transports.id', '=', 'orders.transport_id')
            ->join('pallets', 'pallets.order_id', '=', 'orders.id')
            ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
            ->whereBetween('orders.load_date', [$from, $to])
            ->whereIn('orders.status', Order::closedSalesReportingStatuses())
            ->whereNotNull('orders.transport_id')
            ->whereNull('production_inputs.id')
            ->whereIn('pallets.status', [
                Pallet::STATE_REGISTERED,
                Pallet::STATE_STORED,
                Pallet::STATE_SHIPPED,
            ]);

        if ($user && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('orders.salesperson_id', $user->salesperson->id);
        }

        $results = $query
            ->selectRaw('transports.name, SUM(boxes.net_weight) as netWeight')
            ->groupBy('transports.id', 'transports.name')
            ->get();

        return $results->map(fn ($item) => [
            'name' => $item->name ?? 'Sin transportista',
            'netWeight' => round((float) $item->netWeight, 2),
        ])->values();
    }
}
