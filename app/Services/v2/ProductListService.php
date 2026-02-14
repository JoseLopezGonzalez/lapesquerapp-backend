<?php

namespace App\Services\v2;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductListService
{
    /**
     * Lista productos con filtros y paginación.
     */
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = Product::query();
        $query->with(['family.category', 'family', 'species.fishingGear', 'captureZone']);
        $query = self::applyFilters($query, $request);
        $query->orderBy('name', 'asc');
        $perPage = $request->input('perPage', 14);

        return $query->paginate($perPage);
    }

    /**
     * Aplica filtros al query de productos.
     */
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('species')) {
            $query->whereIn('species_id', $request->species);
        }

        if ($request->has('captureZones')) {
            $query->whereIn('capture_zone_id', $request->captureZones);
        }

        if ($request->has('categories')) {
            $query->whereHas('family', function ($q) use ($request) {
                $q->whereIn('category_id', $request->categories);
            });
        }

        if ($request->has('families')) {
            $query->whereIn('family_id', $request->families);
        }

        if ($request->has('articleGtin')) {
            $query->where('article_gtin', $request->articleGtin);
        }

        if ($request->has('boxGtin')) {
            $query->where('box_gtin', $request->boxGtin);
        }

        if ($request->has('palletGtin')) {
            $query->where('pallet_gtin', $request->palletGtin);
        }

        return $query;
    }
}
