<?php

namespace App\Services\v2;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserListService
{
    /**
     * Lista usuarios con filtros y paginación.
     */
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = User::query();
        $query = self::applyFilters($query, $request);
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $query->orderBy($sort, $direction);
        $perPage = $request->input('perPage', 10);

        return $query->paginate($perPage);
    }

    /**
     * Aplica filtros al query de usuarios.
     */
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('id')) {
            $query->where('id', 'like', '%' . $request->id . '%');
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('created_at')) {
            $createdAt = $request->input('created_at');
            if (!empty($createdAt['start'])) {
                $startDate = date('Y-m-d 00:00:00', strtotime($createdAt['start']));
                $query->where('created_at', '>=', $startDate);
            }
            if (!empty($createdAt['end'])) {
                $endDate = date('Y-m-d 23:59:59', strtotime($createdAt['end']));
                $query->where('created_at', '<=', $endDate);
            }
        }

        return $query;
    }
}
