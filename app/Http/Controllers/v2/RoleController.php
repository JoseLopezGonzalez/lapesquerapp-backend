<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\RoleResource;
use App\Http\Resources\v2\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Role::query();

        // Filtros por ID
        if ($request->has('id')) {
            $text = $request->id;
            $query->where('id', 'like', "%{$text}%");
        }

        // Filtros por nombre
        if ($request->has('name')) {
            $text = $request->name;
            $query->where('name', 'like', "%{$text}%");
        }

        // Ordenar por nombre
        $query->orderBy('name', 'asc');

        // Paginación
        $perPage = $request->input('perPage', 10);

        return RoleResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {}

    /**
     * Display the specified resource.
     */
    public function show($id)
   {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {}

    /* options */
    public function options()
    {
        $roles = Role::select('id', 'name')->get();
        return response()->json($roles);
    }
}
