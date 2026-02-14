<?php

namespace App\Services\v2;

use App\Models\RawMaterialReception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class RawMaterialReceptionListService
{
    /**
     * Lista recepciones de materia prima con filtros y paginación.
     *
     * @param  Request  $request  Request con query params ya validados (IndexRawMaterialReceptionRequest)
     * @return LengthAwarePaginator
     */
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = RawMaterialReception::query();
        $query->with('supplier', 'products.product', 'pallets.reception', 'pallets.boxes.box.productionInputs');

        if ($request->filled('id')) {
            $query->where('id', $request->input('id'));
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }

        if ($request->filled('suppliers')) {
            $query->whereIn('supplier_id', $request->input('suppliers'));
        }

        if ($request->has('dates')) {
            $dates = $request->input('dates');
            if (!empty($dates['start'])) {
                $query->where('date', '>=', date('Y-m-d 00:00:00', strtotime($dates['start'])));
            }
            if (!empty($dates['end'])) {
                $query->where('date', '<=', date('Y-m-d 23:59:59', strtotime($dates['end'])));
            }
        }

        if ($request->filled('species')) {
            $query->whereHas('products.product', function ($q) use ($request) {
                $q->whereIn('species_id', $request->input('species'));
            });
        }

        if ($request->filled('products')) {
            $query->whereHas('products.product', function ($q) use ($request) {
                $q->whereIn('id', $request->input('products'));
            });
        }

        if ($request->filled('notes')) {
            $query->where('notes', 'like', '%' . $request->input('notes') . '%');
        }

        $query->orderBy('date', 'desc');

        $perPage = $request->input('perPage', 12);

        return $query->paginate($perPage);
    }
}
