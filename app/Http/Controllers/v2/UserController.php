<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
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

        // Filtros por rol
        if ($request->has('roles')) {
            $roles = $request->roles;
            $query->whereHas('roles', function ($q) use ($roles) {
                $q->whereIn('name', $roles);
            });
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

        // Cargar roles para la respuesta
        $query->with('roles');

        // Paginación
        $perPage = $request->input('perPage', 10);

        return UserResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validar la solicitud
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenant.users,email',
            'password' => 'required|string|min:8',
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => 'integer|exists:tenant.roles,id',
            'active' => 'sometimes|boolean',
        ]);

        // Usar una transacción para asegurar que ambas operaciones se completen exitosamente
        DB::beginTransaction();

        try {
            // Crear el usuario
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'active' => $validated['active'] ?? true,
            ]);

            // Asignar roles al usuario (múltiples roles)
            $user->roles()->sync($validated['role_ids']);

            // Confirmar la transacción
            DB::commit();

            // Cargar roles para la respuesta
            $user->load('roles');

            // Responder con éxito y código 201 (Created)
            return response()->json([
                'message' => 'Usuario creado correctamente.',
                'data' => new UserResource($user),
            ], 201);
        } catch (Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollBack();

            // Devolver un error interno del servidor
            return response()->json([
                'message' => 'Error al crear el usuario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
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
            'role_ids' => 'sometimes|array|min:1',
            'role_ids.*' => 'integer|exists:tenant.roles,id',
        ]);

        $user->update(array_filter([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'password' => isset($validated['password']) ? Hash::make($validated['password']) : null,
            'active' => $validated['active'] ?? null,
        ]));

        // Asignar roles (múltiples roles)
        if (isset($validated['role_ids'])) {
            $user->roles()->sync($validated['role_ids']);
        }

        // Cargar roles para la respuesta
        $user->load('roles');

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
