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
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }


    // Obtener usuario autenticado
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
