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
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tenant.roles,name',
            'description' => 'nullable|string|max:1000',
        ]);

        $role = Role::create($validated);

        return response()->json([
            'message' => 'Rol creado correctamente.',
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $role = Role::findOrFail($id);
        return new RoleResource($role);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:tenant.roles,name,' . $id,
            'description' => 'nullable|string|max:1000',
        ]);

        $role->update($validated);

        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'data' => new RoleResource($role->fresh()),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Verificar si el rol tiene usuarios asignados
        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el rol porque tiene usuarios asignados.',
                'userMessage' => 'Existen usuarios con este rol. Debe desasignarlos primero.',
            ], 400);
        }

        $role->delete();

        return response()->json([
            'message' => 'Rol eliminado correctamente.',
        ], 200);
    }

    /* options */
    public function options()
    {
        $roles = Role::select('id', 'name')->get();
        return response()->json($roles);
    }
}
