<?php

namespace App\Http\Controllers\v2;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        // Validar los datos del formulario
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Buscar al usuario por email
        $user = User::where('email', $request->email)->first();

        // Verificar si el usuario existe y si la contraseña es correcta
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas son inválidas.',
            ], 401); // Respuesta con código de estado 401 (no autorizado)
        }

        // Verificar si el usuario está activo
        if (!$user->active) {
            return response()->json([
                'message' => 'Su cuenta ha sido desactivada. Contacte con el administrador.',
            ], 403); // Respuesta con código de estado 403 (prohibido)
        }
        

        // Crear un token personal para el usuario
        $token = $user->createToken('auth_token')->plainTextToken;

        // Devolver respuesta exitosa con el token y datos básicos del usuario
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'assignedStoreId' => $user->assigned_store_id,
                'companyName' => $user->company_name,
                'companyLogoUrl' => $user->company_logo_url,
                'role' => $user->roles->pluck('name'), // Si usas roles
            ],
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        // Eliminar solo el token actual en lugar de todos los tokens
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }


    // Obtener usuario autenticado
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
