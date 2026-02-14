<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Pallet;
use App\Services\v2\StockStatisticsService;
use Illuminate\Http\Request;

class StockStatisticsController extends Controller
{
    /**
     * Devuelve estadísticas generales del stock almacenado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Formato de respuesta:
     * {
     *   "totalNetWeight": float,     // Peso neto total (en kg) de todas las cajas en palets almacenados
     *   "totalPallets": int,      // Número total de palets en estado almacenado
     *   "totalBoxes": int,        // Número total de cajas asociadas a palets almacenados
     *   "totalSpecies": int,      // Número de especies distintas presentes en los artículos almacenados
     *   "totalStores": int        // Número total de almacenes distintos que contienen palets almacenados
     * }
     */
    public function totalStockStats()
    {
        $this->authorize('viewAny', Pallet::class);
        $stats = StockStatisticsService::getTotalStockStats();

        return response()->json($stats);
    }

    /**
     * Devuelve el stock total agrupado por especie con porcentaje.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * Formato de respuesta:
     * [
     *   {
     *     "id": int,
     *     "name": string,
     *     "totalNetWeight": float,
     *     "percentage": float
     *   },
     *   ...
     * ]
     */
    public function totalStockBySpeciesStats()
    {
        $this->authorize('viewAny', Pallet::class);
        $stats = StockStatisticsService::getTotalStockBySpeciesStats();

        return response()->json($stats);
    }


}
