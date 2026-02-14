<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\UserResource;
use App\Enums\Role;
use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

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
     * Users have no password; they sign in via magic link or OTP. Use "Reenviar invitación" to send the link.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique(User::class, 'email')->whereNull('deleted_at'),
            ],
            'role' => ['required', 'string', Rule::in(Role::values())],
            'active' => 'sometimes|boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
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
        $this->authorize('view', $user);

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
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique(User::class, 'email')->whereNull('deleted_at')->ignore($user->id),
            ],
            'active' => 'sometimes|boolean',
            'role' => ['sometimes', 'string', Rule::in(Role::values())],
        ]);

        $user->update(array_filter([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'active' => $validated['active'] ?? null,
            'role' => $validated['role'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Soft delete the user (sets deleted_at). Revokes all their sessions.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);

        $user->tokens()->delete();
        $user->delete();
        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    /**
     * Reenviar invitación: envía un magic link por email al usuario.
     * Útil para usuarios invitados (sin contraseña) o para enviar un enlace de acceso a cualquier usuario.
     */
    public function resendInvitation($id)
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        if (!$user->active) {
            return response()->json([
                'message' => 'No se puede reenviar la invitación a un usuario desactivado.',
            ], 403);
        }

        try {
            $sent = app(MagicLinkService::class)->sendMagicLinkToUser($user);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'No se pudo enviar el correo. Compruebe la configuración de email del tenant.',
            ], 500);
        }

        if ($sent === null) {
            return response()->json([
                'message' => 'Configuración de la aplicación incompleta (URL del frontend). Contacte al administrador.',
            ], 500);
        }

        return response()->json([
            'message' => 'Se ha enviado un enlace de acceso al correo del usuario.',
        ], 200);
    }

    /* options */
    public function options()
    {
        $this->authorize('viewAny', User::class);

        $users = User::select('id', 'name')->get();
        return response()->json($users);
    }
}
