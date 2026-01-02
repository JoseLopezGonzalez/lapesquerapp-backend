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
