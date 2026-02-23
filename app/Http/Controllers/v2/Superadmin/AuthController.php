<?php

namespace App\Http\Controllers\v2\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SuperadminMagicLinkToken;
use App\Models\SuperadminUser;
use App\Sanctum\SuperadminPersonalAccessToken;
use App\Services\SuperadminAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

class AuthController extends Controller
{
    private const REQUEST_ACCESS_MESSAGE = 'Si el correo está registrado, recibirás un correo con un enlace y un código para acceder.';

    public function requestAccess(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = SuperadminUser::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => self::REQUEST_ACCESS_MESSAGE]);
        }

        try {
            app(SuperadminAuthService::class)->sendAccessEmail($user);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'No se pudo enviar el correo.'], 500);
        }

        return response()->json(['message' => self::REQUEST_ACCESS_MESSAGE]);
    }

    public function verifyMagicLink(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $hashedToken = hash('sha256', $request->token);

        $record = SuperadminMagicLinkToken::valid()
            ->magicLink()
            ->where('token', $hashedToken)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'El enlace no es válido o ha expirado. Solicita uno nuevo.',
            ], 400);
        }

        $user = SuperadminUser::where('email', $record->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $record->markAsUsed();
        $user->update(['last_login_at' => now('UTC')]);

        return $this->tokenResponse($user);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $record = SuperadminMagicLinkToken::valid()
            ->otp()
            ->where('email', $request->email)
            ->where('otp_code', $request->code)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'El código no es válido o ha expirado. Solicita uno nuevo.',
            ], 400);
        }

        $user = SuperadminUser::where('email', $record->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $record->markAsUsed();
        $user->update(['last_login_at' => now('UTC')]);

        return $this->tokenResponse($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $previousModel = Sanctum::$personalAccessTokenModel;
        Sanctum::usePersonalAccessTokenModel(SuperadminPersonalAccessToken::class);

        $request->user()->currentAccessToken()->delete();

        Sanctum::usePersonalAccessTokenModel($previousModel);

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'last_login_at' => $user->last_login_at,
            ],
        ]);
    }

    private function tokenResponse(SuperadminUser $user): JsonResponse
    {
        $previousModel = Sanctum::$personalAccessTokenModel;
        Sanctum::usePersonalAccessTokenModel(SuperadminPersonalAccessToken::class);

        $token = $user->createToken('superadmin_auth')->plainTextToken;

        Sanctum::usePersonalAccessTokenModel($previousModel);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
