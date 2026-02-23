<?php

namespace App\Http\Controllers\v2\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Superadmin\ImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(
        private ImpersonationService $service
    ) {}

    /**
     * Request consent-based impersonation. Sends approval email to tenant admin.
     */
    public function requestAccess(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate(['target_user_id' => 'required|integer']);

        $superadmin = $request->user();

        $impersonationRequest = $this->service->requestConsent(
            $superadmin,
            $tenant,
            $request->target_user_id
        );

        return response()->json([
            'message' => 'Solicitud de impersonación enviada. Esperando aprobación del usuario.',
            'request_id' => $impersonationRequest->id,
        ]);
    }

    /**
     * Silent impersonation (no consent, logged).
     */
    public function silent(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate(['target_user_id' => 'required|integer']);

        $result = $this->service->silentImpersonate(
            $request->user(),
            $tenant,
            $request->target_user_id
        );

        return response()->json($result);
    }

    /**
     * Generate token for an approved consent request.
     */
    public function generateToken(Request $request, Tenant $tenant): JsonResponse
    {
        $result = $this->service->generateTokenFromRequest(
            $request->user(),
            $tenant
        );

        return response()->json($result);
    }

    /**
     * End an impersonation session.
     */
    public function end(Request $request): JsonResponse
    {
        $request->validate(['log_id' => 'required|integer']);

        $this->service->endSession($request->log_id);

        return response()->json(['message' => 'Sesión de impersonación finalizada.']);
    }
}
