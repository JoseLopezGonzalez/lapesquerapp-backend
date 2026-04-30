<?php

namespace App\Services\Production;

use App\Models\Pallet;
use Illuminate\Support\Facades\DB;

class OrphanStockService
{
    /**
     * Devuelve lotes en stock sin produccion ni recepcion, paginados por lote.
     * Bajo cada lote se anidan los palets que lo contienen con su desglose por producto.
     */
    public function getLots(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $sortDir = ($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $lotSearch = $filters['lot'] ?? null;

        $baseConditions = $this->baseConditions($lotSearch);

        $total = DB::connection('tenant')
            ->table('boxes')
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->where($baseConditions)
            ->distinct()
            ->count('boxes.lot');

        $lots = DB::connection('tenant')
            ->table('boxes')
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->where($baseConditions)
            ->select('boxes.lot')
            ->distinct()
            ->orderBy('boxes.lot', $sortDir)
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->pluck('lot')
            ->all();

        $rows = empty($lots) ? [] : $this->buildRows($lots);

        return [
            'lots'       => $rows,
            'pagination' => [
                'currentPage' => $page,
                'perPage'     => $perPage,
                'total'       => $total,
                'lastPage'    => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    // ========================================================
    // INTERNOS
    // ========================================================

    /**
     * Condiciones SQL comunes a la query de conteo y a la de paginacion.
     * Un palet es "huerfano" cuando:
     *   - Estado en stock (registered o stored)
     *   - Sin reception_id (no viene de recepcion)
     *   - El lot de la caja no existe en productions.lot
     *   - El lot tiene valor
     */
    private function baseConditions(?string $lotSearch): \Closure
    {
        return function ($query) use ($lotSearch) {
            $query->whereIn('pallets.status', [Pallet::STATE_REGISTERED, Pallet::STATE_STORED])
                ->whereNull('pallets.reception_id')
                ->whereNotNull('boxes.lot')
                ->where('boxes.lot', '!=', '')
                ->whereNotExists(fn ($sub) =>
                    $sub->from('productions')->whereColumn('productions.lot', 'boxes.lot')
                );

            if ($lotSearch) {
                $query->where('boxes.lot', 'like', '%' . $lotSearch . '%');
            }
        };
    }

    /**
     * Para los lotes de la pagina actual, carga el detalle de cada palet
     * en una sola query agregada y construye la estructura anidada.
     */
    private function buildRows(array $lots): array
    {
        $detail = DB::connection('tenant')
            ->table('boxes')
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->join('products', 'boxes.article_id', '=', 'products.id')
            ->leftJoin('stored_pallets', 'stored_pallets.pallet_id', '=', 'pallets.id')
            ->leftJoin('stores', 'stores.id', '=', 'stored_pallets.store_id')
            ->whereIn('boxes.lot', $lots)
            ->whereIn('pallets.status', [Pallet::STATE_REGISTERED, Pallet::STATE_STORED])
            ->whereNull('pallets.reception_id')
            ->groupBy(
                'boxes.lot',
                'pallets.id',
                'pallets.status',
                'pallets.created_at',
                'boxes.article_id',
                'products.name',
                'stores.name',
                'stored_pallets.position'
            )
            ->select(
                'boxes.lot',
                'pallets.id as pallet_id',
                'pallets.status as pallet_status',
                'pallets.created_at as pallet_created_at',
                'boxes.article_id as product_id',
                'products.name as product_name',
                'stores.name as store_name',
                'stored_pallets.position as store_position',
                DB::raw('COUNT(boxes.id) as boxes_count'),
                DB::raw('COALESCE(SUM(boxes.net_weight), 0) as weight_kg')
            )
            ->orderBy('boxes.lot')
            ->orderBy('pallets.id')
            ->get();

        // Agrupar: lot → pallet_id → products[]
        $byLot    = [];
        $byPallet = [];

        foreach ($detail as $row) {
            $lot      = $row->lot;
            $palletId = $row->pallet_id;

            if (!isset($byLot[$lot])) {
                $byLot[$lot] = ['totalWeightKg' => 0.0, 'totalBoxes' => 0, 'palletIds' => [], 'productIds' => []];
            }

            $byLot[$lot]['totalWeightKg'] += (float) $row->weight_kg;
            $byLot[$lot]['totalBoxes']    += (int) $row->boxes_count;

            if (!in_array($palletId, $byLot[$lot]['palletIds'])) {
                $byLot[$lot]['palletIds'][] = $palletId;
            }
            if (!in_array($row->product_id, $byLot[$lot]['productIds'])) {
                $byLot[$lot]['productIds'][] = $row->product_id;
            }

            if (!isset($byPallet[$lot][$palletId])) {
                $byPallet[$lot][$palletId] = [
                    'id'          => $palletId,
                    'status'      => $row->pallet_status,
                    'statusLabel' => $this->palletStatusLabel($row->pallet_status),
                    'location'    => $this->buildLocation($row->store_name, $row->store_position),
                    'createdAt'   => $row->pallet_created_at,
                    'weightKg'    => 0.0,
                    'boxesCount'  => 0,
                    'products'    => [],
                ];
            }

            $byPallet[$lot][$palletId]['weightKg']   += (float) $row->weight_kg;
            $byPallet[$lot][$palletId]['boxesCount']  += (int) $row->boxes_count;
            $byPallet[$lot][$palletId]['products'][]   = [
                'id'       => $row->product_id,
                'name'     => $row->product_name,
                'weightKg' => round((float) $row->weight_kg, 3),
                'boxes'    => (int) $row->boxes_count,
            ];
        }

        // Construir el array final respetando el orden de $lots
        return array_map(function (string $lot) use ($byLot, $byPallet) {
            $meta    = $byLot[$lot] ?? ['totalWeightKg' => 0.0, 'totalBoxes' => 0, 'palletIds' => []];
            $pallets = array_values($byPallet[$lot] ?? []);

            foreach ($pallets as &$pallet) {
                $pallet['weightKg']  = round($pallet['weightKg'], 3);
            }
            unset($pallet);

            return [
                'lot'            => $lot,
                'totalWeightKg'  => round($meta['totalWeightKg'], 3),
                'totalBoxes'     => $meta['totalBoxes'],
                'totalPallets'   => count($meta['palletIds']),
                'pallets'        => $pallets,
            ];
        }, $lots);
    }

    private function palletStatusLabel(int $status): string
    {
        return match ($status) {
            Pallet::STATE_REGISTERED => 'Registrado',
            Pallet::STATE_STORED     => 'Almacenado',
            Pallet::STATE_SHIPPED    => 'Enviado',
            Pallet::STATE_PROCESSED  => 'Procesado',
            default                  => "Estado {$status}",
        };
    }

    private function buildLocation(?string $storeName, ?string $position): ?string
    {
        if (!$storeName) {
            return null;
        }

        return $position ? "{$storeName} / {$position}" : $storeName;
    }
}
