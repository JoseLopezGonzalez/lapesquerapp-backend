<?php

namespace App\Services\v2;

use App\Models\Pallet;
use App\Models\Species;
use App\Models\StoredPallet;
use App\Services\Production\ProductionCostResolver;
use Illuminate\Support\Facades\DB;

class StockStatisticsService
{
    public static function getTotalStockStats(): array
    {
        // Filtrar solo cajas disponibles (que no han sido usadas en producción)
        // IMPORTANTE: Incluye palets registrados (status = 1) Y almacenados (status = 2)
        // Esto incluye palets registrados que aún no tienen almacén asignado
        $totalWeight = Pallet::query()
            ->inStock()  // Incluye registered (status=1) y stored (status=2)
            ->joinBoxes()
            ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
            ->whereNull('production_inputs.id') // Solo cajas sin production_inputs
            ->sum('boxes.net_weight');

        // Contar todos los palets en stock (registrados y almacenados)
        // Incluye palets registrados sin almacén asignado
        $totalPallets = Pallet::inStock()->count();

        // Contar solo cajas disponibles de palets en stock
        // Incluye cajas de palets registrados sin almacén
        $totalBoxes = Pallet::inStock()
            ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
            ->whereNull('production_inputs.id')
            ->count('pallet_boxes.id');

        // Contar especies distintas solo de cajas disponibles
        // Incluye especies de palets registrados sin almacén
        $totalSpecies = Pallet::inStock()
            ->joinProducts()
            ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
            ->whereNull('production_inputs.id')
            ->distinct('products.species_id')
            ->count('products.species_id');

        // Solo contar almacenes de palets almacenados (status = 2)
        // Los palets registrados (status = 1) no tienen almacén asignado
        $totalStores = StoredPallet::stored()
            ->distinct('stored_pallets.store_id')
            ->count('stored_pallets.store_id');

        $costStats = self::computeCostStats((float) $totalWeight, (int) $totalBoxes);

        return [
            'totalNetWeight'        => round($totalWeight, 2),
            'totalPallets'          => $totalPallets,
            'totalBoxes'            => $totalBoxes,
            'totalSpecies'          => $totalSpecies,
            'totalStores'           => $totalStores,
            'totalStockCost'        => $costStats['totalStockCost'],
            'stockCostPerKg'        => $costStats['stockCostPerKg'],
            'coveredNetWeightKg'    => $costStats['coveredNetWeightKg'],
            'uncoveredNetWeightKg'  => $costStats['uncoveredNetWeightKg'],
            'costCoverageWeightPct' => $costStats['costCoverageWeightPct'],
            'coveredBoxes'          => $costStats['coveredBoxes'],
            'uncoveredBoxes'        => $costStats['uncoveredBoxes'],
            'costCoverageBoxesPct'  => $costStats['costCoverageBoxesPct'],
        ];
    }

    /**
     * Calcula el coste total del stock disponible y las métricas de cobertura de coste.
     *
     * Estrategia:
     * - Para cajas de recepción: el precio/kg se obtiene directamente de
     *   raw_material_reception_products mediante un JOIN en SQL (una sola consulta).
     * - Para cajas de producción (sin recepción): se resuelve el coste por lote+producto
     *   usando ProductionCostResolver, que cachea por par (lot, article_id) para evitar
     *   queries redundantes cuando hay muchas cajas del mismo lote.
     *
     * La cobertura indica qué porcentaje del stock (en kg y en cajas) tiene coste calculable,
     * para que el front pueda evaluar cuánto de fiable es el coste total mostrado.
     */
    private static function computeCostStats(float $totalNetWeight, int $totalBoxesCount): array
    {
        // Una sola consulta SQL para obtener todas las cajas disponibles en stock,
        // junto con el precio/kg de recepción cuando aplica.
        $rows = DB::connection('tenant')
            ->table('boxes as b')
            ->join('pallet_boxes as pb', 'pb.box_id', '=', 'b.id')
            ->join('pallets as pal', 'pal.id', '=', 'pb.pallet_id')
            ->leftJoin('production_inputs as pi', 'pi.box_id', '=', 'b.id')
            ->leftJoin('raw_material_reception_products as rmp', function ($join) {
                $join->on('rmp.reception_id', '=', 'pal.reception_id')
                    ->on('rmp.product_id', '=', 'b.article_id')
                    ->on('rmp.lot', '=', 'b.lot');
            })
            ->whereIn('pal.status', [Pallet::STATE_REGISTERED, Pallet::STATE_STORED])
            ->whereNull('pi.id')
            ->select([
                'b.id as box_id',
                'b.net_weight',
                'b.article_id',
                'b.lot',
                'pal.reception_id',
                'rmp.price as reception_price',
            ])
            ->get();

        // Para cajas de producción, resolver coste por par único (lot, article_id)
        // para aprovechar el caché interno del resolver y minimizar queries.
        $resolver = new ProductionCostResolver();
        $productionCostMap = [];

        $rows
            ->filter(fn ($r) => $r->reception_id === null && $r->lot !== null && $r->lot !== '')
            ->unique(fn ($r) => $r->article_id . ':' . $r->lot)
            ->each(function ($r) use ($resolver, &$productionCostMap) {
                $key = $r->article_id . ':' . $r->lot;
                $productionCostMap[$key] = $resolver->getProductionLotProductCostPerKg(
                    (string) $r->lot,
                    (int) $r->article_id
                );
            });

        $totalStockCost    = null;
        $coveredNetWeight  = 0.0;
        $uncoveredNetWeight = 0.0;
        $coveredBoxes      = 0;
        $uncoveredBoxes    = 0;

        foreach ($rows as $row) {
            $weight = (float) $row->net_weight;

            if ($row->reception_id !== null) {
                $costPerKg = $row->reception_price !== null ? (float) $row->reception_price : null;
            } else {
                $key = $row->article_id . ':' . $row->lot;
                $costPerKg = $productionCostMap[$key] ?? null;
            }

            if ($costPerKg !== null) {
                $totalStockCost    = ($totalStockCost ?? 0.0) + ($weight * $costPerKg);
                $coveredNetWeight += $weight;
                $coveredBoxes++;
            } else {
                $uncoveredNetWeight += $weight;
                $uncoveredBoxes++;
            }
        }

        $stockCostPerKg = ($totalStockCost !== null && $totalNetWeight > 0)
            ? round($totalStockCost / $totalNetWeight, 4)
            : null;

        $costCoverageWeightPct = $totalNetWeight > 0
            ? round($coveredNetWeight / $totalNetWeight * 100, 2)
            : 0.0;

        $costCoverageBoxesPct = $totalBoxesCount > 0
            ? round($coveredBoxes / $totalBoxesCount * 100, 2)
            : 0.0;

        return [
            'totalStockCost'        => $totalStockCost !== null ? round($totalStockCost, 2) : null,
            'stockCostPerKg'        => $stockCostPerKg,
            'coveredNetWeightKg'    => round($coveredNetWeight, 2),
            'uncoveredNetWeightKg'  => round($uncoveredNetWeight, 2),
            'costCoverageWeightPct' => $costCoverageWeightPct,
            'coveredBoxes'          => $coveredBoxes,
            'uncoveredBoxes'        => $uncoveredBoxes,
            'costCoverageBoxesPct'  => $costCoverageBoxesPct,
        ];
    }

    public static function getSpeciesTotalsRaw(): \Illuminate\Support\Collection
    {
        // Filtrar solo cajas disponibles (que no han sido usadas en producción)
        // IMPORTANTE: Incluye palets registrados (status = 1) Y almacenados (status = 2)
        // Esto incluye palets registrados que aún no tienen almacén asignado
        return Pallet::inStock()  // Incluye registered (status=1) y stored (status=2)
            ->joinProducts()
            ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
            ->whereNull('production_inputs.id') // Solo cajas sin production_inputs
            ->selectRaw('products.species_id, SUM(boxes.net_weight) as totalNetWeight')
            ->groupBy('products.species_id')
            ->get();
    }

    public static function getTotalStockBySpeciesStats(): array
    {
        $rows = self::getSpeciesTotalsRaw();

        $speciesList = Species::whereIn('id', $rows->pluck('species_id'))->get()->keyBy('id');

        $data = $rows->map(function ($row) use ($speciesList) {
            $species = $speciesList[$row->species_id] ?? null;
            return [
                'id' => $row->species_id,
                'name' => $species?->name ?? 'Desconocida',
                'totalNetWeight' => round($row->totalNetWeight, 2),
            ];
        })->filter(fn($item) => $item['id'] !== null)->values();

        $totalNetWeight = $data->sum('totalNetWeight');

        return $data->map(function ($item) use ($totalNetWeight) {
            $item['percentage'] = $totalNetWeight > 0
                ? round(($item['totalNetWeight'] / $totalNetWeight) * 100, 2)
                : 0;
            return $item;
        })->sortByDesc('totalNetWeight')->values()->toArray();
    }
}
