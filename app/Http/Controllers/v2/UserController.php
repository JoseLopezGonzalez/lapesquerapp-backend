<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexUserRequest;
use App\Http\Requests\v2\StoreUserRequest;
use App\Http\Requests\v2\UpdateUserRequest;
use App\Http\Resources\v2\UserResource;
use App\Models\User;
use App\Services\MagicLinkService;
use App\Services\v2\UserListService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexUserRequest $request)
    {
        return UserResource::collection(UserListService::list($request));
    }

    /**
     * Store a newly created resource in storage.
     * Users have no password; they sign in via magic link or OTP. Use "Reenviar invitación" to send the link.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

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
    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::findOrFail($id);
        $validated = $request->validated();

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
                'message' => 'Acción no autorizada.',
                'userMessage' => 'No se puede reenviar la invitación a un usuario desactivado.',
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
        $this->authorize('viewOptions', User::class);

        $users = User::select('id', 'name')->get();
        return response()->json($users);
    }
}
