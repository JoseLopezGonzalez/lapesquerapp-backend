<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Services\v2\OrderStatisticsService;
use Illuminate\Http\Request;

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
    public function totalNetWeightStats(Request $request)
    {
        $validated = $request->validate([
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date',
            'speciesId' => 'nullable|integer|exists:tenant.species,id',
        ]);

        $result = OrderStatisticsService::getNetWeightStatsComparedToLastYear(
            $validated['dateFrom'],
            $validated['dateTo'],
            $validated['speciesId'] ?? null
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
    public function totalAmountStats(Request $request)
    {
        // Aumentar límites para consultas pesadas
        set_time_limit(300); // 5 minutos
        ini_set('memory_limit', '512M'); // 512MB de memoria

        $validated = $request->validate([
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date',
            'speciesId' => 'nullable|integer|exists:tenant.species,id',
        ]);

        $stats = OrderStatisticsService::getAmountStatsComparedToLastYear(
            $validated['dateFrom'],
            $validated['dateTo'],
            $validated['speciesId'] ?? null
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

    public function orderRankingStats(Request $request)
    {
        // Aumentar límites para consultas pesadas
        set_time_limit(300); // 5 minutos
        ini_set('memory_limit', '512M'); // 512MB de memoria

        $validated = $request->validate([
            'groupBy' => 'required|in:client,country,product',
            'valueType' => 'required|in:totalAmount,totalQuantity',
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date',
            'speciesId' => 'nullable|integer|exists:tenant.species,id',
        ]);

        $results = OrderStatisticsService::getOrderRankingStats(
            $validated['groupBy'],
            $validated['valueType'],
            $validated['dateFrom'] . ' 00:00:00',
            $validated['dateTo'] . ' 23:59:59',
            $validated['speciesId'] ?? null
        );

        return response()->json($results);
    }

}
