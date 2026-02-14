<?php

namespace App\Services\v2;

use App\Models\CeboDispatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CeboDispatchListService
{
    /**
     * Lista despachos de cebo con filtros y paginación.
     */
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = CeboDispatch::query();
        $query->with('supplier', 'products.product');
        $query = self::applyFilters($query, $request);
        $query->orderBy('date', 'desc');
        $perPage = $request->input('perPage', 12);

        return $query->paginate($perPage);
    }

    /**
     * Aplica filtros al query de despachos de cebo.
     */
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        if ($request->has('suppliers')) {
            $query->whereIn('supplier_id', $request->suppliers);
        }

        if ($request->has('dates')) {
            $dates = $request->input('dates');
            if (!empty($dates['start'])) {
                $startDate = date('Y-m-d 00:00:00', strtotime($dates['start']));
                $query->where('date', '>=', $startDate);
            }
            if (!empty($dates['end'])) {
                $endDate = date('Y-m-d 23:59:59', strtotime($dates['end']));
                $query->where('date', '<=', $endDate);
            }
        }

        if ($request->has('species')) {
            $query->whereHas('products.product', function ($q) use ($request) {
                $q->whereIn('species_id', $request->species);
            });
        }

        if ($request->has('products')) {
            $query->whereHas('products.product', function ($q) use ($request) {
                $q->whereIn('id', $request->products);
            });
        }

        if ($request->filled('notes')) {
            $query->where('notes', 'like', '%' . $request->notes . '%');
        }

        if ($request->filled('export_type')) {
            $query->where('export_type', $request->export_type);
        }

        return $query;
    }
}
