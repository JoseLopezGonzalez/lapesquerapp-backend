<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\RequestAccessRequest;
use App\Http\Requests\v2\VerifyMagicLinkRequest;
use App\Http\Requests\v2\VerifyOtpRequest;
use App\Models\ExternalUser;
use App\Models\MagicLinkToken;
use App\Models\Tenant;
use App\Models\TenantBlocklist;
use App\Models\User;
use App\Services\AuthActorService;
use App\Services\MagicLinkService;
use App\Services\Superadmin\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function __construct(
        protected AuthActorService $actors
    ) {}

    private const REQUEST_ACCESS_MESSAGE = 'Si el correo está registrado y activo, recibirás un correo con un enlace y un código para acceder.';

    public function login(Request $request)
    {
        return response()->json([
            'message' => 'Usa el botón "Acceder" en la pantalla de inicio de sesión. Recibirás un correo con un enlace y un código.',
        ], 400);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if ($user instanceof ExternalUser) {
            $user->refresh();
        }

        if (! $this->actors->isActive($user)) {
            return response()->json([
                'message' => 'Acción no autorizada.',
                'userMessage' => 'El usuario no existe o está desactivado.',
            ], 403);
        }

        return response()->json($this->buildActorPayload($user, includeFeatures: true));
    }

    public function requestAccess(RequestAccessRequest $request)
    {
        if ($this->isBlocked($request->email, $request->ip())) {
            return response()->json(['message' => self::REQUEST_ACCESS_MESSAGE], 200);
        }

        $actor = $this->actors->resolveByEmail($request->email);

        if (! $this->actors->isActive($actor)) {
            $this->recordLoginAttempt($request, false);

            return response()->json(['message' => self::REQUEST_ACCESS_MESSAGE], 200);
        }

        try {
            $sent = app(MagicLinkService::class)->sendAccessEmailToUser($actor);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No se pudo enviar el correo. Compruebe la configuración de email del tenant.',
            ], 500);
        }

        if (! $sent) {
            return response()->json([
                'message' => 'Configuración de la aplicación incompleta. Contacte al administrador.',
            ], 500);
        }

        $this->recordLoginAttempt($request, true, $actor->email);

        return response()->json(['message' => self::REQUEST_ACCESS_MESSAGE], 200);
    }

    public function requestMagicLink(RequestAccessRequest $request)
    {
        return $this->requestAccess($request);
    }

    public function verifyMagicLink(VerifyMagicLinkRequest $request)
    {
        $hashedToken = hash('sha256', $request->token);

        $record = MagicLinkToken::valid()
            ->magicLink()
            ->where('token', $hashedToken)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'El enlace no es válido o ha expirado. Solicita uno nuevo.',
            ], 400);
        }

        $actor = $this->actors->resolveByEmail($record->email);

        if (! $this->actors->isActive($actor)) {
            return response()->json([
                'message' => 'Acción no autorizada.',
                'userMessage' => 'El usuario no existe o está desactivado.',
            ], 403);
        }

        $record->markAsUsed();
        $this->recordLoginAttempt($request, true, $actor->email);

        return $this->tokenResponse($actor);
    }

    public function requestOtp(RequestAccessRequest $request)
    {
        return $this->requestAccess($request);
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $record = MagicLinkToken::valid()
            ->otp()
            ->where('email', $request->email)
            ->where('otp_code', $request->code)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'El código no es válido o ha expirado. Solicita uno nuevo.',
            ], 400);
        }

        $actor = $this->actors->resolveByEmail($record->email);

        if (! $this->actors->isActive($actor)) {
            return response()->json([
                'message' => 'Acción no autorizada.',
                'userMessage' => 'El usuario no existe o está desactivado.',
            ], 403);
        }

        $record->markAsUsed();
        $this->recordLoginAttempt($request, true, $record->email);

        return $this->tokenResponse($actor);
    }

    private function getActiveFeatures(): array
    {
        try {
            if (! app()->bound('currentTenant') || ! app('currentTenant')) {
                return [];
            }

            $subdomain = app('currentTenant');
            $tenant = Tenant::where('subdomain', $subdomain)->first();

            if (! $tenant) {
                return [];
            }

            $flags = app(FeatureFlagService::class)->getEffectiveFlags($tenant);

            return array_keys(array_filter($flags));
        } catch (\Throwable) {
            return [];
        }
    }

    private function isBlocked(string $email, ?string $ip): bool
    {
        try {
            $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
            if (! $tenant) {
                return false;
            }

            $tenantObj = Tenant::where('subdomain', $tenant)->first();
            if (! $tenantObj) {
                return false;
            }

            $cacheKey = "blocklist:{$tenantObj->id}:{$email}:{$ip}";

            return Cache::remember($cacheKey, 300, function () use ($tenantObj, $email, $ip) {
                $query = TenantBlocklist::where('tenant_id', $tenantObj->id)->active();

                $emailBlocked = (clone $query)->where('type', 'email')->where('value', $email)->exists();
                $ipBlocked = $ip ? (clone $query)->where('type', 'ip')->where('value', $ip)->exists() : false;

                return $emailBlocked || $ipBlocked;
            });
        } catch (\Throwable) {
            return false;
        }
    }

    private function recordLoginAttempt(Request $request, bool $success, ?string $email = null): void
    {
        try {
            DB::connection('tenant')->table('login_attempts')->insert([
                'email' => $email ?? ($request->email ?? ''),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'success' => $success,
                'attempted_at' => now('UTC'),
            ]);
        } catch (\Throwable) {
        }
    }

    private function tokenResponse(User|ExternalUser $user): \Illuminate\Http\JsonResponse
    {
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->buildActorPayload($user),
        ]);
    }

    private function buildActorPayload(User|ExternalUser $user, bool $includeFeatures = false): array
    {
        $isExternal = $user instanceof ExternalUser;
        $salespersonId = ! $isExternal ? $user->salesperson?->id : null;
        $fieldOperatorId = ! $isExternal ? $user->fieldOperator?->id : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'assigned_store_id' => $isExternal ? null : $user->assigned_store_id,
            'assignedStoreId' => $isExternal ? null : $user->assigned_store_id,
            'company_name' => $user->company_name,
            'companyName' => $user->company_name,
            'company_logo_url' => $isExternal ? null : $user->company_logo_url,
            'companyLogoUrl' => $isExternal ? null : $user->company_logo_url,
            'active' => $isExternal ? $user->is_active : $user->active,
            'role' => $isExternal ? null : $user->role,
            'salespersonId' => $salespersonId,
            'fieldOperatorId' => $fieldOperatorId,
            'isFieldOperator' => ! $isExternal && $fieldOperatorId !== null,
            'actorType' => $this->actors->actorType($user),
            'externalUserType' => $isExternal ? $user->type : null,
            'allowedStoreIds' => $this->actors->allowedStoreIds($user),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'features' => $includeFeatures && ! $isExternal ? $this->getActiveFeatures() : [],
        ];
    }
}
