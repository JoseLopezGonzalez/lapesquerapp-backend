<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\RequestAccessRequest;
use App\Http\Requests\v2\VerifyMagicLinkRequest;
use App\Http\Requests\v2\VerifyOtpRequest;
use App\Models\MagicLinkToken;
use App\Models\Tenant;
use App\Models\TenantBlocklist;
use App\Models\User;
use App\Services\MagicLinkService;
use App\Services\Superadmin\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * El acceso ya no es por contraseña. Usar un solo botón "Acceder" (magic link + OTP en un email).
     */
    public function login(Request $request)
    {
        return response()->json([
            'message' => 'Usa el botón "Acceder" en la pantalla de inicio de sesión. Recibirás un correo con un enlace y un código.',
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
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'assigned_store_id' => $user->assigned_store_id,
            'company_name'      => $user->company_name,
            'company_logo_url'  => $user->company_logo_url,
            'active'            => $user->active,
            'role'              => $user->role,
            'created_at'        => $user->created_at,
            'updated_at'        => $user->updated_at,
            'features'          => $this->getActiveFeatures(),
        ]);
    }

    /**
     * Get the effective feature flags for the current tenant.
     * Returns a flat array of enabled flag keys, or empty array on error.
     */
    private function getActiveFeatures(): array
    {
        try {
            if (!app()->bound('currentTenant') || !app('currentTenant')) {
                return [];
            }

            $subdomain = app('currentTenant');
            $tenant    = Tenant::where('subdomain', $subdomain)->first();

            if (!$tenant) {
                return [];
            }

            $flags = app(FeatureFlagService::class)->getEffectiveFlags($tenant);

            return array_keys(array_filter($flags));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Mensaje genérico para no revelar si el email existe. */
    private const REQUEST_ACCESS_MESSAGE = 'Si el correo está registrado y activo, recibirás un correo con un enlace y un código para acceder.';

    /**
     * Solicitar acceso: un solo email con magic link + código OTP (flujo tipo Claude).
     * El usuario pulsa "Acceder" y puede usar el enlace o el código según el dispositivo.
     */
    public function requestAccess(RequestAccessRequest $request)
    {
        if ($this->isBlocked($request->email, $request->ip())) {
            return response()->json(['message' => self::REQUEST_ACCESS_MESSAGE], 200);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->active) {
            $this->recordLoginAttempt($request, false);

            return response()->json(['message' => self::REQUEST_ACCESS_MESSAGE], 200);
        }

        try {
            $sent = app(MagicLinkService::class)->sendAccessEmailToUser($user);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'No se pudo enviar el correo. Compruebe la configuración de email del tenant.',
            ], 500);
        }

        if (!$sent) {
            return response()->json([
                'message' => 'Configuración de la aplicación incompleta. Contacte al administrador.',
            ], 500);
        }

        $this->recordLoginAttempt($request, true);

        return response()->json(['message' => self::REQUEST_ACCESS_MESSAGE], 200);
    }

    /**
     * Solicitar magic link por email.
     * Envía el mismo email unificado (enlace + código) que requestAccess.
     */
    public function requestMagicLink(RequestAccessRequest $request)
    {
        return $this->requestAccess($request);
    }

    /**
     * Canjear token de magic link e iniciar sesión.
     */
    public function verifyMagicLink(VerifyMagicLinkRequest $request)
    {
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
                'message' => 'Acción no autorizada.',
                'userMessage' => 'El usuario no existe o está desactivado.',
            ], 403);
        }

        $record->markAsUsed();
        $this->recordLoginAttempt($request, true, $user->email);

        return $this->tokenResponse($user);
    }

    /**
     * Solicitar código OTP por email.
     * Envía el mismo email unificado (enlace + código) que requestAccess.
     */
    public function requestOtp(RequestAccessRequest $request)
    {
        return $this->requestAccess($request);
    }

    /**
     * Canjear código OTP e iniciar sesión.
     */
    public function verifyOtp(VerifyOtpRequest $request)
    {
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
                'message' => 'Acción no autorizada.',
                'userMessage' => 'El usuario no existe o está desactivado.',
            ], 403);
        }

        $record->markAsUsed();
        $this->recordLoginAttempt($request, true, $record->email);

        return $this->tokenResponse($user);
    }

    /**
     * Check if an email or IP is blocked for the current tenant.
     * Results are cached for 5 minutes per tenant.
     */
    private function isBlocked(string $email, ?string $ip): bool
    {
        try {
            $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
            if (!$tenant) {
                return false;
            }

            $tenantObj = \App\Models\Tenant::where('subdomain', $tenant)->first();
            if (!$tenantObj) {
                return false;
            }

            $cacheKey = "blocklist:{$tenantObj->id}:{$email}:{$ip}";

            return Cache::remember($cacheKey, 300, function () use ($tenantObj, $email, $ip) {
                $query = TenantBlocklist::where('tenant_id', $tenantObj->id)->active();

                $emailBlocked = (clone $query)->where('type', 'email')->where('value', $email)->exists();
                $ipBlocked    = $ip ? (clone $query)->where('type', 'ip')->where('value', $ip)->exists() : false;

                return $emailBlocked || $ipBlocked;
            });
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Record a login attempt in the tenant's login_attempts table.
     * Silently ignores errors to never break the auth flow.
     */
    private function recordLoginAttempt(Request $request, bool $success, ?string $email = null): void
    {
        try {
            DB::connection('tenant')->table('login_attempts')->insert([
                'email'        => $email ?? ($request->email ?? ''),
                'ip_address'   => $request->ip(),
                'user_agent'   => substr((string) $request->userAgent(), 0, 500),
                'success'      => $success,
                'attempted_at' => now('UTC'),
            ]);
        } catch (\Throwable) {
            // Never break auth flow due to logging errors
        }
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
