<?php

namespace App\Services\v2;

use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

use function normalizeDateToBusiness;

class CustomerOrderHistoryService
{
    /**
     * Obtener el historial completo de pedidos del cliente.
     * Devuelve un resumen de todos los productos pedidos, incluyendo trend vs período anterior.
     */
    public static function getOrderHistory(Customer $customer, Request $request): array
    {
        $availableYears = self::getAvailableYears($customer);

        $ordersQuery = Order::where('customer_id', $customer->id);
        self::applyOrderHistoryFilters($ordersQuery, $request);

        $orders = $ordersQuery
            ->with([
                'plannedProductDetails.product',
                'pallets.boxes.box.product',
                'pallets.boxes.box.productionInputs',
            ])
            ->orderBy('load_date', 'desc')
            ->get();

        $history = self::buildProductHistory($orders);
        $previousPeriodDates = self::getPreviousPeriodDates($request);
        $previousPeriodNetWeights = self::getPreviousPeriodNetWeights($customer, $previousPeriodDates);

        self::calculateAggregates($history, $previousPeriodDates, $previousPeriodNetWeights);

        return [
            'available_years' => $availableYears,
            'data' => array_values($history),
        ];
    }

    /**
     * Calcular años disponibles desde todos los pedidos históricos.
     */
    private static function getAvailableYears(Customer $customer): array
    {
        return Order::where('customer_id', $customer->id)
            ->whereNotNull('load_date')
            ->selectRaw('YEAR(load_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Construir array de historial agrupado por producto.
     */
    private static function buildProductHistory($orders): array
    {
        $history = [];

        foreach ($orders as $order) {
            foreach ($order->productDetails as $detail) {
                $productId = $detail['product']['id'];

                if (!isset($history[$productId])) {
                    $history[$productId] = [
                        'product' => [
                            'id' => $detail['product']['id'],
                            'name' => $detail['product']['name'],
                            'a3erpCode' => $detail['product']['a3erpCode'] ?? null,
                            'facilcomCode' => $detail['product']['facilcomCode'] ?? null,
                            'species_id' => $detail['product']['species_id'] ?? null,
                        ],
                        'total_boxes' => 0,
                        'total_net_weight' => 0,
                        'average_unit_price' => 0,
                        'last_order_date' => self::formatLoadDate($order->load_date),
                        'lines' => [],
                        'total_amount' => 0,
                    ];
                }

                $history[$productId]['total_boxes'] += $detail['boxes'];
                $history[$productId]['total_net_weight'] += $detail['netWeight'];
                $history[$productId]['total_amount'] += $detail['total'];

                $loadDate = self::formatLoadDate($order->load_date);

                $history[$productId]['lines'][] = [
                    'order_id' => $order->id,
                    'formatted_id' => $order->formatted_id,
                    'load_date' => $loadDate,
                    'boxes' => (int) $detail['boxes'],
                    'net_weight' => round((float) $detail['netWeight'], 2),
                    'unit_price' => $detail['unitPrice'],
                    'subtotal' => round((float) $detail['subtotal'], 2),
                    'total' => round((float) $detail['total'], 2),
                ];

                if ($loadDate && (!$history[$productId]['last_order_date'] || strcmp($loadDate, $history[$productId]['last_order_date']) > 0)) {
                    $history[$productId]['last_order_date'] = $loadDate;
                }
            }
        }

        return $history;
    }

    /**
     * Formatear load_date a YYYY-MM-DD.
     */
    private static function formatLoadDate($loadDate): ?string
    {
        if (!$loadDate) {
            return null;
        }

        if ($loadDate instanceof Carbon || $loadDate instanceof \DateTime) {
            return $loadDate->format('Y-m-d');
        }

        return date('Y-m-d', strtotime($loadDate));
    }

    /**
     * Obtener pesos netos del período anterior para todos los productos.
     */
    private static function getPreviousPeriodNetWeights(Customer $customer, ?array $previousPeriodDates): array
    {
        if (!$previousPeriodDates) {
            return [];
        }

        $previousOrders = Order::where('customer_id', $customer->id)
            ->whereBetween('load_date', [$previousPeriodDates['from'], $previousPeriodDates['to']])
            ->with([
                'plannedProductDetails.product',
                'pallets.boxes.box.product',
                'pallets.boxes.box.productionInputs',
            ])
            ->get();

        $netWeights = [];
        foreach ($previousOrders as $order) {
            foreach ($order->productDetails as $detail) {
                $productId = $detail['product']['id'];
                $netWeights[$productId] = ($netWeights[$productId] ?? 0) + $detail['netWeight'];
            }
        }

        return $netWeights;
    }

    /**
     * Calcular agregados: average_unit_price, ordenar líneas, trend.
     */
    private static function calculateAggregates(array &$history, ?array $previousPeriodDates, array $previousPeriodNetWeights): void
    {
        foreach ($history as &$product) {
            if ($product['total_net_weight'] > 0) {
                $product['average_unit_price'] = round($product['total_amount'] / $product['total_net_weight'], 2);
            } else {
                $product['average_unit_price'] = 0;
            }

            usort($product['lines'], fn ($a, $b) => strcmp($b['load_date'], $a['load_date']));

            $product['total_boxes'] = (int) $product['total_boxes'];
            $product['total_net_weight'] = round((float) $product['total_net_weight'], 2);
            $product['total_amount'] = round((float) $product['total_amount'], 2);

            if ($previousPeriodDates) {
                $productId = $product['product']['id'];
                $previousNetWeight = $previousPeriodNetWeights[$productId] ?? 0;
                $product['trend'] = self::calculateTrendValue($product['total_net_weight'], $previousNetWeight);
            }
        }
    }

    /**
     * Aplicar filtros a la query de pedidos según parámetros de request.
     */
    private static function applyOrderHistoryFilters($query, Request $request): void
    {
        if ($request->has('date_from') && $request->has('date_to')) {
            $dateFrom = normalizeDateToBusiness($request->date_from);
            $dateTo = normalizeDateToBusiness($request->date_to);
            $query->whereBetween('load_date', [$dateFrom, $dateTo]);
            return;
        }

        if ($request->has('year')) {
            $year = (int) $request->year;
            $query->whereYear('load_date', $year);
            return;
        }

        if ($request->has('period')) {
            $period = $request->period;
            $now = Carbon::now(config('app.business_timezone', 'Europe/Madrid'));

            switch ($period) {
                case 'month':
                    $query->whereYear('load_date', $now->year)
                          ->whereMonth('load_date', $now->month);
                    break;
                case 'quarter':
                    $startOfQuarter = $now->copy()->startOfQuarter();
                    $endOfQuarter = $now->copy()->endOfQuarter();
                    $query->whereBetween('load_date', [
                        $startOfQuarter->format('Y-m-d 00:00:00'),
                        $endOfQuarter->format('Y-m-d 23:59:59'),
                    ]);
                    break;
                case 'year':
                    $query->whereYear('load_date', $now->year);
                    break;
            }
        }
    }

    /**
     * Obtener las fechas del período anterior según el filtro aplicado.
     */
    private static function getPreviousPeriodDates(Request $request): ?array
    {
        if ($request->has('date_from') && $request->has('date_to')) {
            $dateFrom = Carbon::parse($request->date_from);
            $dateTo = Carbon::parse($request->date_to);
            $daysDiff = $dateFrom->diffInDays($dateTo);
            $previousDateEnd = $dateFrom->copy()->subDay()->endOfDay();
            $previousDateStart = $previousDateEnd->copy()->subDays($daysDiff)->startOfDay();

            return [
                'from' => $previousDateStart->format('Y-m-d 00:00:00'),
                'to' => $previousDateEnd->format('Y-m-d 23:59:59'),
            ];
        }

        if ($request->has('year')) {
            $previousYear = (int) $request->year - 1;
            return [
                'from' => $previousYear . '-01-01 00:00:00',
                'to' => $previousYear . '-12-31 23:59:59',
            ];
        }

        if ($request->has('period')) {
            $now = Carbon::now(config('app.business_timezone', 'Europe/Madrid'));
            switch ($request->period) {
                case 'month':
                    $prev = $now->copy()->subMonth();
                    return [
                        'from' => $prev->copy()->startOfMonth()->format('Y-m-d 00:00:00'),
                        'to' => $prev->copy()->endOfMonth()->format('Y-m-d 23:59:59'),
                    ];
                case 'quarter':
                    $prev = $now->copy()->subQuarter();
                    return [
                        'from' => $prev->copy()->startOfQuarter()->format('Y-m-d 00:00:00'),
                        'to' => $prev->copy()->endOfQuarter()->format('Y-m-d 23:59:59'),
                    ];
                case 'year':
                    $prev = $now->copy()->subYear();
                    return [
                        'from' => $prev->copy()->startOfYear()->format('Y-m-d 00:00:00'),
                        'to' => $prev->copy()->endOfYear()->format('Y-m-d 23:59:59'),
                    ];
                default:
                    return null;
            }
        }

        return null;
    }

    /**
     * Calcular el trend comparando el peso neto del período actual vs período anterior.
     */
    private static function calculateTrendValue(float $currentNetWeight, float $previousNetWeight): array
    {
        if ($previousNetWeight == 0) {
            return [
                'direction' => 'stable',
                'percentage' => 0,
            ];
        }

        $percentage = (($currentNetWeight - $previousNetWeight) / $previousNetWeight) * 100;
        $absolutePercentage = abs($percentage);

        $direction = 'stable';
        if ($absolutePercentage >= 5) {
            $direction = $percentage > 0 ? 'up' : 'down';
        }

        return [
            'direction' => $direction,
            'percentage' => round($absolutePercentage, 2),
        ];
    }
}
