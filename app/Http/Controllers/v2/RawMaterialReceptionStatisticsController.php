<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\ReceptionChartDataRequest;
use App\Models\RawMaterialReception;
use App\Services\v2\RawMaterialReceptionStatisticsService;

class RawMaterialReceptionStatisticsController extends Controller
{
    /**
     * Devuelve datos de recepciones de materia prima agrupados por período (día, semana o mes)
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function receptionChartData(ReceptionChartDataRequest $request)
    {
        $this->authorize('viewAny', RawMaterialReception::class);

        $validated = $request->validated();

        $dateFrom = $validated['dateFrom'] . ' 00:00:00';
        $dateTo = $validated['dateTo'] . ' 23:59:59';
        $valueType = $validated['valueType'];
        $groupBy = $validated['groupBy'] ?? 'day';

        $results = RawMaterialReceptionStatisticsService::getReceptionChartData(
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

