<?php

namespace App\Services\v2;

use App\Models\ProspectCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProspectCategoryListService
{
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = self::applyFilters(ProspectCategory::query(), $request);

        return $query
            ->orderBy('name')
            ->paginate(min((int) $request->input('perPage', 12), 100));
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
            $query->where('name', 'like', '%'.$request->name.'%');
        }
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return $query;
    }
}
