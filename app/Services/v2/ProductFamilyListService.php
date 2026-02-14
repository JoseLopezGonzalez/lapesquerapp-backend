<?php

namespace App\Services\v2;

use App\Models\ProductFamily;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductFamilyListService
{
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = ProductFamily::query();
        $query->with('category');
        $query = self::applyFilters($query, $request);
        $query->orderBy('name', 'asc');
        $perPage = $request->input('perPage', 12);

        return $query->paginate($perPage);
    }

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
        if ($request->has('categoryId')) {
            $query->where('category_id', $request->categoryId);
        }
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }
        return $query;
    }
}
