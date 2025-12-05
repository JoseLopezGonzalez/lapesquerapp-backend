<?php

namespace App\Services\Production;

use App\Models\Production;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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

        $query->orderBy('opened_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Create a new production
     */
    public function create(array $data): Production
    {
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
        $production->update($data);
        $production->load(['species', 'captureZone', 'records.process']);

        return $production;
    }

    /**
     * Delete a production
     */
    public function delete(Production $production): bool
    {
        return $production->delete();
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

