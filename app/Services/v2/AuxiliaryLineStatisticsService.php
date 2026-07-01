<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\Order;
use App\Models\OrderAuxiliaryLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas de líneas auxiliares (productos no pesqueros).
 *
 * Son completamente independientes de las estadísticas pesqueras: nunca tocan
 * order_planned_product_details, palets ni cajas. El subtotal se calcula como
 * SUM(unit_price * quantity) y el IVA desde taxes.rate.
 */
class AuxiliaryLineStatisticsService
{
    /**
     * Query base: líneas auxiliares de pedidos cerrados (finished/incident) en el rango,
     * con scoping implícito por comercial (igual que OrderStatisticsService).
     */
    private static function baseQuery(string $from, string $to, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        $query = OrderAuxiliaryLine::query()
            ->join('orders', 'orders.id', '=', 'order_auxiliary_lines.order_id')
            ->leftJoin('taxes', 'order_auxiliary_lines.tax_id', '=', 'taxes.id')
            ->whereBetween('orders.load_date', [$from, $to])
            ->whereIn('orders.status', Order::closedSalesReportingStatuses());

        if ($user && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('orders.salesperson_id', $user->salesperson->id);
        }

        return $query;
    }

    private const SUBTOTAL_SQL = 'order_auxiliary_lines.unit_price * order_auxiliary_lines.quantity';

    private const TOTAL_SQL = 'order_auxiliary_lines.unit_price * order_auxiliary_lines.quantity * (1 + COALESCE(taxes.rate, 0) / 100)';

    /**
     * @return array{subtotal: float, tax: float, total: float}
     */
    public static function calculateTotalAmount(string $from, string $to, ?User $user = null): array
    {
        $result = self::baseQuery($from, $to, $user)
            ->toBase()
            ->selectRaw('SUM('.self::SUBTOTAL_SQL.') as subtotal, SUM('.self::TOTAL_SQL.') as total')
            ->first();

        $subtotal = (float) ($result->subtotal ?? 0);
        $total = (float) ($result->total ?? 0);

        return [
            'subtotal' => $subtotal,
            'tax' => $total - $subtotal,
            'total' => $total,
        ];
    }

    public static function getAmountStatsComparedToLastYear(string $dateFrom, string $dateTo, ?User $user = null): array
    {
        $range = OrderStatisticsService::prepareDateRangeAndPrevious($dateFrom, $dateTo);

        $current = self::calculateTotalAmount($range['from'], $range['to'], $user);
        $previous = self::calculateTotalAmount($range['fromPrev'], $range['toPrev'], $user);

        return [
            'value' => round($current['total'], 2),
            'subtotal' => round($current['subtotal'], 2),
            'tax' => round($current['tax'], 2),

            'comparisonValue' => round($previous['total'], 2),
            'comparisonSubtotal' => round($previous['subtotal'], 2),
            'comparisonTax' => round($previous['tax'], 2),

            'percentageChange' => OrderStatisticsService::compareTotals($current['total'], $previous['total']) !== null
                ? round(OrderStatisticsService::compareTotals($current['total'], $previous['total']), 2)
                : null,

            'range' => $range,
        ];
    }

    /**
     * Ranking de artículos auxiliares vendidos (por importe base sin IVA).
     *
     * @return Collection<int, array{name: string, quantity: float, unit: string, subtotal: float}>
     */
    public static function getByProduct(string $from, string $to, ?User $user = null): Collection
    {
        $nameExpr = 'COALESCE(auxiliary_products.name, order_auxiliary_lines.description)';

        return self::baseQuery($from, $to, $user)
            ->leftJoin('auxiliary_products', 'order_auxiliary_lines.auxiliary_product_id', '=', 'auxiliary_products.id')
            ->toBase()
            ->selectRaw(
                $nameExpr.' as name, order_auxiliary_lines.unit as unit, '
                .'SUM(order_auxiliary_lines.quantity) as quantity, '
                .'SUM('.self::SUBTOTAL_SQL.') as subtotal'
            )
            ->groupBy(DB::raw($nameExpr), 'order_auxiliary_lines.unit')
            ->orderByDesc('subtotal')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'quantity' => round((float) $row->quantity, 3),
                'unit' => $row->unit,
                'subtotal' => round((float) $row->subtotal, 2),
            ]);
    }

    /**
     * Importe de líneas auxiliares agrupado por cliente.
     *
     * @return Collection<int, array{customerName: string, subtotal: float, total: float}>
     */
    public static function getByCustomer(string $from, string $to, ?User $user = null): Collection
    {
        return self::baseQuery($from, $to, $user)
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->toBase()
            ->selectRaw(
                'customers.name as customer_name, '
                .'SUM('.self::SUBTOTAL_SQL.') as subtotal, '
                .'SUM('.self::TOTAL_SQL.') as total'
            )
            ->groupBy('customers.name')
            ->orderByDesc('subtotal')
            ->get()
            ->map(fn ($row) => [
                'customerName' => (string) $row->customer_name,
                'subtotal' => round((float) $row->subtotal, 2),
                'total' => round((float) $row->total, 2),
            ]);
    }

    /**
     * Serie temporal de importe auxiliar agrupada por día, semana o mes.
     *
     * @return Collection<int, array{date: string, subtotal: float, total: float}>
     */
    public static function getChartData(string $from, string $to, string $groupBy = 'day', ?User $user = null): Collection
    {
        $dateExpr = match ($groupBy) {
            'month' => "DATE_FORMAT(orders.load_date, '%Y-%m')",
            'week' => "DATE_FORMAT(orders.load_date, '%x-W%v')",
            default => "DATE_FORMAT(orders.load_date, '%Y-%m-%d')",
        };

        return self::baseQuery($from, $to, $user)
            ->toBase()
            ->selectRaw(
                $dateExpr.' as date, '
                .'SUM('.self::SUBTOTAL_SQL.') as subtotal, '
                .'SUM('.self::TOTAL_SQL.') as total'
            )
            ->groupBy(DB::raw($dateExpr))
            ->orderBy(DB::raw($dateExpr))
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'subtotal' => round((float) $row->subtotal, 2),
                'total' => round((float) $row->total, 2),
            ]);
    }

    /**
     * Top N artículos auxiliares por importe vendido (base sin IVA).
     *
     * @return Collection<int, array{name: string, quantity: float, unit: string, subtotal: float}>
     */
    public static function getTopAuxiliaryProducts(string $from, string $to, int $limit = 10, ?User $user = null): Collection
    {
        return self::getByProduct($from, $to, $user)->take($limit)->values();
    }
}
