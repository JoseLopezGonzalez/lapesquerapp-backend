<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Services\v2\CeboDispatchStatisticsService;
use Illuminate\Http\Request;

class CeboDispatchStatisticsController extends Controller
{
    /**
     * Devuelve datos de salidas de cebo agrupados por período (día, semana o mes)
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
    public function dispatchChartData(Request $request)
    {
        $validated = $request->validate([
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date',
            'speciesId' => 'nullable|integer|exists:tenant.species,id',
            'familyId' => 'nullable|integer|exists:tenant.product_families,id',
            'categoryId' => 'nullable|integer|exists:tenant.product_categories,id',
            'valueType' => 'required|in:amount,quantity',
            'groupBy' => 'nullable|in:day,week,month',
        ]);

        $dateFrom = $validated['dateFrom'] . ' 00:00:00';
        $dateTo = $validated['dateTo'] . ' 23:59:59';
        $valueType = $validated['valueType'];
        $groupBy = $validated['groupBy'] ?? 'day';

        $results = CeboDispatchStatisticsService::getDispatchChartData(
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

