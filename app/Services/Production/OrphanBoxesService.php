<?php

namespace App\Services\Production;

use App\Models\Box;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class OrphanBoxesService
{
    /**
     * Cajas sin fila en pallet_boxes (no pertenecen a ningún palet).
     * Solo lectura orientada al panel operativo / auditoría de stock.
     */
    public function getPaginated(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 25);

        $base = Box::query()
            ->whereDoesntHave('palletBox');

        $this->applyFilters($base, $filters);

        $totalBoxes = (clone $base)->count();
        $totalWeightKg = (float) (clone $base)->sum('net_weight');

        $sortBy = \in_array($filters['sort_by'] ?? '', ['id', 'lot', 'created_at', 'net_weight'], true)
            ? $filters['sort_by']
            : 'id';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        /** @var LengthAwarePaginator $paginator */
        $paginator = (clone $base)
            ->with(['article:id,name'])
            ->withCount('productionInputs')
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);

        $paginator->setCollection(
            collect($paginator->items())->map(fn (Box $box) => $this->serializeBox($box))->values()
        );

        return [
            'summary' => [
                'totalOrphanBoxes' => $totalBoxes,
                'totalOrphanWeightKg' => round($totalWeightKg, 3),
            ],
            'boxes' => array_values($paginator->items()),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['lot'])) {
            $query->where('lot', 'like', '%'.$filters['lot'].'%');
        }

        if (! empty($filters['article_id'])) {
            $query->where('article_id', $filters['article_id']);
        }
    }

    private function serializeBox(Box $box): array
    {
        $product = $box->relationLoaded('article') ? $box->article : null;

        return [
            'id' => $box->id,
            'lot' => $box->lot,
            'articleId' => $box->article_id,
            'product' => $product
                ? ['id' => $product->id, 'name' => $product->name]
                : null,
            'gs1128' => $box->gs1_128,
            'grossWeight' => $box->gross_weight !== null ? (float) $box->gross_weight : null,
            'netWeight' => $box->net_weight !== null ? round((float) $box->net_weight, 3) : null,
            'manualCostPerKg' => $box->manual_cost_per_kg !== null ? (float) $box->manual_cost_per_kg : null,
            'usedAsProductionInput' => ($box->production_inputs_count ?? 0) > 0,
            'productionInputsCount' => (int) ($box->production_inputs_count ?? 0),
            'createdAt' => $box->created_at?->toIso8601String(),
        ];
    }
}
