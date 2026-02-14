<?php

namespace App\Services\v2;

use App\Models\Salesperson;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class SalespersonListService
{
    /**
     * Lista comerciales con filtros y paginación.
     *
     * @param  Request  $request  Request con query params ya validados (IndexSalespersonRequest)
     * @return LengthAwarePaginator
     */
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = Salesperson::query();

        if ($request->filled('id')) {
            $query->where('id', $request->input('id'));
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 10);

        return $query->paginate($perPage);
    }
}
