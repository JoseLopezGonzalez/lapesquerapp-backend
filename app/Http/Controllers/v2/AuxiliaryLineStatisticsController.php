<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\AuxiliaryLineChartDataRequest;
use App\Http\Requests\v2\AuxiliaryLineStatsRequest;
use App\Models\Order;
use App\Services\v2\AuxiliaryLineStatisticsService;

class AuxiliaryLineStatisticsController extends Controller
{
    /**
     * Importe total de líneas auxiliares en un rango, comparado con el año anterior.
     */
    public function totalAmountStats(AuxiliaryLineStatsRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $validated = $request->validated();
        $stats = AuxiliaryLineStatisticsService::getAmountStatsComparedToLastYear(
            $validated['dateFrom'],
            $validated['dateTo'],
            $request->user()
        );

        return response()->json($stats);
    }

    /**
     * Ranking de artículos auxiliares vendidos por importe.
     */
    public function byProduct(AuxiliaryLineStatsRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $validated = $request->validated();
        $results = AuxiliaryLineStatisticsService::getByProduct(
            $validated['dateFrom'].' 00:00:00',
            $validated['dateTo'].' 23:59:59',
            $request->user()
        );

        return response()->json($results);
    }

    /**
     * Importe de líneas auxiliares agrupado por cliente.
     */
    public function byCustomer(AuxiliaryLineStatsRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $validated = $request->validated();
        $results = AuxiliaryLineStatisticsService::getByCustomer(
            $validated['dateFrom'].' 00:00:00',
            $validated['dateTo'].' 23:59:59',
            $request->user()
        );

        return response()->json($results);
    }

    /**
     * Serie temporal de importe auxiliar (day|week|month).
     */
    public function chartData(AuxiliaryLineChartDataRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $validated = $request->validated();
        $results = AuxiliaryLineStatisticsService::getChartData(
            $validated['dateFrom'].' 00:00:00',
            $validated['dateTo'].' 23:59:59',
            $validated['groupBy'] ?? 'day',
            $request->user()
        );

        return response()->json($results);
    }
}
