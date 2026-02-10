<?php

namespace App\Http\Controllers\v2;

use App\Mail\MagicLinkEmail;
use App\Mail\OtpEmail;
use App\Models\MagicLinkToken;
use App\Models\User;
use App\Services\MagicLinkService;
use App\Services\TenantMailConfigService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const MAGIC_LINK_EXPIRES_MINUTES = 10;

    /**
     * El acceso ya no es por contraseña. Usar magic link u OTP.
     */
    public function login(Request $request)
    {
        return response()->json([
            'message' => 'El acceso se realiza mediante enlace o código enviado por correo. Usa "Enviar enlace" o "Enviar código" en la pantalla de inicio de sesión.',
        ], 400);
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
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'assigned_store_id' => $user->assigned_store_id,
            'company_name' => $user->company_name,
            'company_logo_url' => $user->company_logo_url,
            'active' => $user->active,
            'role' => $user->role,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    /**
     * Solicitar magic link por email.
     */
    public function requestMagicLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->active) {
            return response()->json([
                'message' => 'Si el correo está registrado y activo, recibirás un enlace para iniciar sesión.',
            ], 200);
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
                'message' => 'Configuración de la aplicación incompleta. Contacte al administrador.',
            ], 500);
        }

        return response()->json([
            'message' => 'Si el correo está registrado y activo, recibirás un enlace para iniciar sesión.',
        ], 200);
    }

    /**
     * Canjear token de magic link e iniciar sesión.
     */
    public function verifyMagicLink(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $hashedToken = hash('sha256', $request->token);

        $record = MagicLinkToken::valid()
            ->magicLink()
            ->where('token', $hashedToken)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'El enlace no es válido o ha expirado. Solicita uno nuevo.',
            ], 400);
        }

        $user = User::where('email', $record->email)->first();

        if (!$user || !$user->active) {
            return response()->json([
                'message' => 'El usuario no existe o está desactivado.',
            ], 403);
        }

        $record->markAsUsed();

        return $this->tokenResponse($user);
    }

    /**
     * Solicitar código OTP por email.
     */
    public function requestOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->active) {
            return response()->json([
                'message' => 'Si el correo está registrado y activo, recibirás un código para iniciar sesión.',
            ], 200);
        }

        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(self::MAGIC_LINK_EXPIRES_MINUTES);

        MagicLinkToken::create([
            'email' => $user->email,
            'token' => hash('sha256', $code . $user->email . now()->timestamp),
            'type' => MagicLinkToken::TYPE_OTP,
            'otp_code' => $code,
            'expires_at' => $expiresAt,
        ]);

        try {
            app(TenantMailConfigService::class)->configureTenantMailer();
            \Illuminate\Support\Facades\Mail::to($user->email)->send(
                new OtpEmail($user->email, $code, self::MAGIC_LINK_EXPIRES_MINUTES)
            );
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'No se pudo enviar el correo. Compruebe la configuración de email del tenant.',
            ], 500);
        }

        return response()->json([
            'message' => 'Si el correo está registrado y activo, recibirás un código para iniciar sesión.',
        ], 200);
    }

    /**
     * Canjear código OTP e iniciar sesión.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $record = MagicLinkToken::valid()
            ->otp()
            ->where('email', $request->email)
            ->where('otp_code', $request->code)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'El código no es válido o ha expirado. Solicita uno nuevo.',
            ], 400);
        }

        $user = User::where('email', $record->email)->first();

        if (!$user || !$user->active) {
            return response()->json([
                'message' => 'El usuario no existe o está desactivado.',
            ], 403);
        }

        $record->markAsUsed();

        return $this->tokenResponse($user);
    }

    private function tokenResponse(User $user): \Illuminate\Http\JsonResponse
    {
        $token = $user->createToken('auth_token')->plainTextToken;

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
                'role' => $user->role,
            ],
        ]);
    }
}
