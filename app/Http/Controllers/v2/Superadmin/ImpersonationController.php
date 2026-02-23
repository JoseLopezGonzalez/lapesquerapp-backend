<?php

namespace App\Http\Controllers\v2\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationLog;
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
        $request->validate([
            'target_user_id' => 'required|integer',
            'reason'         => 'nullable|string|max:500',
        ]);

        $impersonationRequest = $this->service->requestConsent(
            $request->user(),
            $tenant,
            $request->target_user_id,
            $request->reason
        );

        return response()->json([
            'message'    => 'Solicitud de impersonación enviada. Esperando aprobación del usuario.',
            'request_id' => $impersonationRequest->id,
        ]);
    }

    /**
     * Silent impersonation (no consent, logged). Reason is mandatory.
     */
    public function silent(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'target_user_id' => 'required|integer',
            'reason'         => 'required|string|max:500',
        ]);

        $result = $this->service->silentImpersonate(
            $request->user(),
            $tenant,
            $request->target_user_id,
            $request->reason
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
     * End an impersonation session (from the impersonated user side).
     */
    public function end(Request $request): JsonResponse
    {
        $request->validate(['log_id' => 'required|integer']);

        $this->service->endSession($request->log_id);

        return response()->json(['message' => 'Sesión de impersonación finalizada.']);
    }

    /**
     * End a specific session from the superadmin panel.
     */
    public function endFromPanel(ImpersonationLog $log): JsonResponse
    {
        $this->service->endSession($log->id);

        return response()->json(['message' => 'Sesión de impersonación finalizada desde el panel.']);
    }

    /**
     * Paginated history of all impersonation sessions.
     */
    public function logs(Request $request): JsonResponse
    {
        $history = $this->service->getHistory(
            $request->query('tenant_id') ? (int) $request->query('tenant_id') : null,
            $request->query('superadmin_user_id') ? (int) $request->query('superadmin_user_id') : null,
            $request->query('from'),
            (int) $request->query('per_page', 20)
        );

        return response()->json([
            'data' => collect($history->items())->map(fn ($log) => $this->formatLog($log)),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
                'total'        => $history->total(),
            ],
        ]);
    }

    /**
     * Active impersonation sessions (started in last 2h, not ended).
     */
    public function activeSessions(): JsonResponse
    {
        $sessions = $this->service->getActiveSessions();

        return response()->json([
            'data'  => $sessions->map(fn ($log) => $this->formatLog($log)),
            'total' => $sessions->count(),
        ]);
    }

    private function formatLog(ImpersonationLog $log): array
    {
        $durationMinutes = $log->ended_at
            ? (int) $log->started_at->diffInMinutes($log->ended_at)
            : null;

        return [
            'id'               => $log->id,
            'superadmin'       => $log->superadminUser?->name,
            'tenant'           => $log->tenant?->subdomain,
            'tenant_id'        => $log->tenant_id,
            'target_user_id'   => $log->target_user_id,
            'mode'             => $log->mode,
            'reason'           => $log->reason,
            'started_at'       => $log->started_at,
            'ended_at'         => $log->ended_at,
            'duration_minutes' => $durationMinutes,
        ];
    }
}
