<?php

namespace App\Services\v2;

use App\Models\FieldOperator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class FieldOperatorListService
{
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = FieldOperator::query()->with('user');

        if ($request->filled('id')) {
            $query->where('id', $request->integer('id'));
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->filled('userId')) {
            $query->where('user_id', $request->integer('userId'));
        }

        return $query->orderBy('name')->paginate($request->input('perPage', 10));
    }
}
