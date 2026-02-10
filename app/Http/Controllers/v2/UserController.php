<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\UserResource;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::query();

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

        // Filtros por email
        if ($request->has('email')) {
            $text = $request->email;
            $query->where('email', 'like', "%{$text}%");
        }

        // Filtro por rol
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filtros por fecha de creación
        if ($request->has('created_at')) {
            $createdAt = $request->input('created_at');
            if (isset($createdAt['start'])) {
                $startDate = date('Y-m-d 00:00:00', strtotime($createdAt['start']));
                $query->where('created_at', '>=', $startDate);
            }
            if (isset($createdAt['end'])) {
                $endDate = date('Y-m-d 23:59:59', strtotime($createdAt['end']));
                $query->where('created_at', '<=', $endDate);
            }
        }

        // Ordenar por nombre o fecha de creación
        $query->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'));

        // Paginación
        $perPage = $request->input('perPage', 10);

        return UserResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenant.users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', 'string', Rule::in(Role::values())],
            'active' => 'sometimes|boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json([
            'message' => 'Usuario obtenido correctamente.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:tenant.users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
            'active' => 'sometimes|boolean',
            'role' => ['sometimes', 'string', Rule::in(Role::values())],
        ]);

        $user->update(array_filter([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'password' => isset($validated['password']) ? Hash::make($validated['password']) : null,
            'active' => $validated['active'] ?? null,
            'role' => $validated['role'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    /* options */
    public function options()
    {
        $users = User::select('id', 'name')->get();
        return response()->json($users);
    }
}
