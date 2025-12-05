<?php

namespace App\Services\v2;

use App\Models\Pallet;
use App\Models\Species;
use App\Models\StoredPallet;

class StockStatisticsService
{
    public static function getTotalStockStats(): array
    {
        // Filtrar solo cajas disponibles (que no han sido usadas en producción)
        // Incluye palets registrados (state_id = 1) y almacenados (state_id = 2)
        $totalWeight = Pallet::query()
            ->inStock()  // Incluye registered y stored
            ->joinBoxes()
            ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
            ->whereNull('production_inputs.id') // Solo cajas sin production_inputs
            ->sum('boxes.net_weight');

        $totalPallets = Pallet::inStock()->count();

        // Contar solo cajas disponibles
        $totalBoxes = Pallet::inStock()
            ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
            ->whereNull('production_inputs.id')
            ->count('pallet_boxes.id');

        // Contar especies distintas solo de cajas disponibles
        $totalSpecies = Pallet::inStock()
            ->joinProducts()
            ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
            ->whereNull('production_inputs.id')
            ->distinct('products.species_id')
            ->count('products.species_id');


        $totalStores = StoredPallet::stored()
            ->distinct('stored_pallets.store_id')
            ->count('stored_pallets.store_id');

        return [
            'totalNetWeight' => round($totalWeight, 2),
            'totalPallets' => $totalPallets,
            'totalBoxes' => $totalBoxes,
            'totalSpecies' => $totalSpecies,
            'totalStores' => $totalStores,
        ];
    }

    public static function getSpeciesTotalsRaw(): \Illuminate\Support\Collection
    {
        // Filtrar solo cajas disponibles (que no han sido usadas en producción)
        // Incluye palets registrados (state_id = 1) y almacenados (state_id = 2)
        return Pallet::inStock()  // Incluye registered y stored
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
