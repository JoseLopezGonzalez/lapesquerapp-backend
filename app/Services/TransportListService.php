<?php

namespace App\Services;

use App\Models\Transport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransportListService
{
    public function list(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Transport::query();

        if (! empty($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        if (! empty($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (! empty($filters['address'])) {
            $query->where('address', 'like', '%' . $filters['address'] . '%');
        }

        $query->orderBy('name', 'asc');

        return $query->paginate($perPage);
    }
}
