<?php

namespace App\Services\Production;

use App\Models\Production;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use function normalizeDateToBusiness;

class ProductionService
{
    /**
     * List productions with filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Production::query();
        $query->with(['species', 'captureZone', 'records']);

        if (isset($filters['lot'])) {
            $query->where('lot', 'like', "%{$filters['lot']}%");
        }

        if (isset($filters['species_id'])) {
            $query->where('species_id', $filters['species_id']);
        }

        if (isset($filters['status'])) {
            if ($filters['status'] === 'open') {
                $query->whereNotNull('opened_at')->whereNull('closed_at');
            } elseif ($filters['status'] === 'closed') {
                $query->whereNotNull('closed_at');
            }
        }

        // Orden: fecha del proceso raíz más temprano (primer nodo); si no hay raíz con started_at, opened_at del lote.
        $query->orderByRaw(
            'COALESCE((
                SELECT MIN(pr.started_at)
                FROM production_records AS pr
                WHERE pr.production_id = productions.id
                  AND pr.parent_record_id IS NULL
            ), productions.opened_at) DESC'
        );
        $query->orderByDesc('productions.id');

        return $query->paginate($perPage);
    }

    /**
     * Create a new production
     */
    public function create(array $data): Production
    {
        if (isset($data['date'])) {
            $data['date'] = normalizeDateToBusiness($data['date']);
        }
        $production = Production::create($data);
        $production->open();
        $production->load(['species', 'captureZone', 'records.process']);

        return $production;
    }

    /**
     * Update a production
     */
    public function update(Production $production, array $data): Production
    {
        if (isset($data['date'])) {
            $data['date'] = normalizeDateToBusiness($data['date']);
        }
        $production->update($data);
        $production->load(['species', 'captureZone', 'records.process']);

        return $production;
    }

    /**
     * Delete a production
     */
    public function delete(Production $production): bool
    {
        if ($production->isClosed()) {
            throw new \RuntimeException('No se puede eliminar una producción cerrada definitivamente. Debe reabrirla antes de eliminarla.');
        }

        return $production->delete();
    }

    /**
     * Delete multiple productions
     */
    public function deleteMultiple(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $closed = Production::whereIn('id', $ids)->whereNotNull('closed_at')->pluck('lot');
            if ($closed->isNotEmpty()) {
                $lots = $closed->implode(', ');
                throw new \RuntimeException("No se pueden eliminar producciones cerradas: {$lots}. Debe reabrirlas antes de eliminarlas.");
            }

            return Production::whereIn('id', $ids)->delete();
        });
    }

    /**
     * Get production with reconciliation
     */
    public function getWithReconciliation(int $id): array
    {
        $production = Production::with(['species', 'captureZone', 'records.process'])
            ->findOrFail($id);

        return [
            'production' => $production,
            'reconciliation' => $production->getDetailedReconciliationByProduct(),
        ];
    }
}
