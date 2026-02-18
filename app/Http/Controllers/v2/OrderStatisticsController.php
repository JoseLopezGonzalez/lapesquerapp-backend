<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\OrderRankingStatsRequest;
use App\Http\Requests\v2\OrderSalesChartDataRequest;
use App\Http\Requests\v2\OrderTotalAmountStatsRequest;
use App\Http\Requests\v2\OrderTotalNetWeightStatsRequest;
use App\Models\Order;
use App\Services\v2\OrderStatisticsService;

class OrderStatisticsController extends Controller
{
    /**
     * Devuelve estadísticas de peso neto de pedidos en un rango de fechas,
     * comparadas con el mismo rango del año anterior.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Formato de respuesta:
     * {
     *   "value": float,               // Total del peso neto en el rango actual
     *   "comparisonValue": float,    // Total del peso neto en el mismo rango del año anterior
     *   "percentageChange": float|null, // Diferencia porcentual entre ambos periodos
     *   "range": {
     *     "from": string,            // Fecha de inicio del rango actual (YYYY-MM-DD HH:MM:SS)
     *     "to": string,              // Fecha de fin del rango actual
     *     "fromPrev": string,        // Fecha de inicio del rango del año anterior
     *     "toPrev": string           // Fecha de fin del rango del año anterior
     *   }
     * }
     */
    public function totalNetWeightStats(OrderTotalNetWeightStatsRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $validated = $request->validated();
        $result = OrderStatisticsService::getNetWeightStatsComparedToLastYear(
            $validated['dateFrom'],
            $validated['dateTo'],
            $validated['speciesId'] ?? null,
            $request->user()
        );

        return response()->json($result);
    }

    /**
     * Devuelve estadísticas de importe total de pedidos en un rango de fechas,
     * comparadas con el mismo rango del año anterior.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Formato de respuesta:
     * {
     *   "value": float,                 // Importe total del rango actual (subtotal + impuestos)
     *   "subtotal": float,             // Subtotal sin impuestos del rango actual
     *   "tax": float,                  // Importe de impuestos del rango actual
     *
     *   "comparisonValue": float,      // Importe total del mismo rango del año anterior
     *   "comparisonSubtotal": float,   // Subtotal del mismo rango del año anterior
     *   "comparisonTax": float,        // Impuestos del mismo rango del año anterior
     *
     *   "percentageChange": float|null, // Diferencia porcentual del importe total entre ambos periodos
     *
     *   "range": {
     *     "from": string,              // Fecha de inicio del rango actual (YYYY-MM-DD HH:MM:SS)
     *     "to": string,                // Fecha de fin del rango actual
     *     "fromPrev": string,          // Fecha de inicio del rango del año anterior
     *     "toPrev": string             // Fecha de fin del rango del año anterior
     *   }
     * }
     */
    public function totalAmountStats(OrderTotalAmountStatsRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        // Aumentar límites para consultas pesadas
        $limits = config('exports.operations.statistics');
        if ($limits) {
            ini_set('memory_limit', $limits['memory_limit']);
            set_time_limit($limits['max_execution_time']);
        }

        $validated = $request->validated();
        $stats = OrderStatisticsService::getAmountStatsComparedToLastYear(
            $validated['dateFrom'],
            $validated['dateTo'],
            $validated['speciesId'] ?? null,
            $request->user()
        );

        return response()->json($stats);
    }

    /**
     * Devuelve un ranking de pedidos agrupado por cliente, país o producto,
     * basado en la cantidad total (kg) o el importe total (€), dentro de un rango de fechas,
     * y opcionalmente filtrado por especie.
     *
     * Parámetros esperados (query params):
     * - groupBy: string (client | country | product) → define cómo agrupar los resultados
     * - valueType: string (totalAmount | totalQuantity) → define si se ordena por importe o cantidad
     * - dateFrom: string (formato YYYY-MM-DD) → fecha de inicio del rango
     * - dateTo: string (formato YYYY-MM-DD) → fecha final del rango
     * - speciesId: int|null (opcional) → ID de la especie por la que filtrar
     *
     * Formato de respuesta:
     * [
     *   {
     *     "name": "Cliente A | España | Producto X",
     *     "value": float // totalAmount o totalQuantity según valueType
     *   },
     *   ...
     * ]
     *
     * Ejemplo de respuesta con groupBy=client y valueType=totalAmount:
     * [
     *   { "name": "Congelados Brisamar", "value": 12830.50 },
     *   { "name": "Frostmar S.L.", "value": 9740.00 },
     *   ...
     * ]
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function orderRankingStats(OrderRankingStatsRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        // Aumentar límites para consultas pesadas
        $limits = config('exports.operations.statistics');
        if ($limits) {
            ini_set('memory_limit', $limits['memory_limit']);
            set_time_limit($limits['max_execution_time']);
        }

        $validated = $request->validated();
        $results = OrderStatisticsService::getOrderRankingStats(
            $validated['groupBy'],
            $validated['valueType'],
            $validated['dateFrom'] . ' 00:00:00',
            $validated['dateTo'] . ' 23:59:59',
            $validated['speciesId'] ?? null,
            $request->user()
        );

        return response()->json($results);
    }

    /**
     * Devuelve datos de ventas agrupados por período (día, semana o mes)
     * con filtros opcionales por especie, familia o categoría.
     *
     * Parámetros esperados (query params):
     * - dateFrom: string (formato YYYY-MM-DD) → fecha de inicio del rango
     * - dateTo: string (formato YYYY-MM-DD) → fecha final del rango
     * - valueType: string (amount | quantity) → tipo de valor a retornar
     * - groupBy: string (day | week | month) → agrupación temporal (default: day)
     * - speciesId: int|null (opcional) → ID de la especie por la que filtrar
     * - familyId: int|null (opcional) → ID de la familia por la que filtrar
     * - categoryId: int|null (opcional) → ID de la categoría por la que filtrar
     *
     * Formato de respuesta:
     * [
     *   {
     *     "date": "2025-01-01",
     *     "value": 1234.56
     *   },
     *   ...
     * ]
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function salesChartData(OrderSalesChartDataRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $validated = $request->validated();
        $dateFrom = $validated['dateFrom'] . ' 00:00:00';
        $dateTo = $validated['dateTo'] . ' 23:59:59';
        $valueType = $validated['valueType'];
        $groupBy = $validated['groupBy'] ?? 'day';

        $results = OrderStatisticsService::getSalesChartData(
            $dateFrom,
            $dateTo,
            $valueType,
            $groupBy,
            $validated['speciesId'] ?? null,
            $validated['familyId'] ?? null,
            $validated['categoryId'] ?? null
        );

        return response()->json($results);
    }

}
