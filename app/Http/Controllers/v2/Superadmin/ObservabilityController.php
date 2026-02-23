<?php

namespace App\Http\Controllers\v2\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SuperadminUser;
use App\Models\SystemAlert;
use App\Models\Tenant;
use App\Services\Superadmin\ObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObservabilityController extends Controller
{
    public function __construct(
        private ObservabilityService $service
    ) {}

    // ── Error logs ────────────────────────────────────────────────────────────

    /**
     * Error logs for a specific tenant.
     */
    public function tenantErrorLogs(Request $request, Tenant $tenant): JsonResponse
    {
        $logs = $this->service->getTenantErrorLogs(
            $tenant,
            (int) $request->query('per_page', 20),
            (int) $request->query('days', 30)
        );

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * Global error logs from all tenants.
     */
    public function globalErrorLogs(Request $request): JsonResponse
    {
        $logs = $this->service->getGlobalErrorLogs(
            (int) $request->query('per_page', 50),
            (int) $request->query('days', 30)
        );

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    // ── Activity feed ─────────────────────────────────────────────────────────

    /**
     * Recent activity feed aggregating impersonation, migrations, alerts, and tenant changes.
     */
    public function activityFeed(Request $request): JsonResponse
    {
        $feed = $this->service->getActivityFeed(
            (int) $request->query('limit', 50)
        );

        return response()->json(['data' => $feed]);
    }

    // ── Queue health ──────────────────────────────────────────────────────────

    /**
     * Queue health status.
     */
    public function queueHealth(): JsonResponse
    {
        $health = $this->service->getQueueHealth();

        return response()->json(['data' => $health]);
    }

    // ── System alerts ─────────────────────────────────────────────────────────

    /**
     * List system alerts.
     */
    public function alerts(Request $request): JsonResponse
    {
        $query = SystemAlert::with('tenant:id,subdomain')
            ->orderByDesc('created_at');

        if ($request->query('severity')) {
            $query->where('severity', $request->query('severity'));
        }

        $resolved = $request->query('resolved', 'false');
        if ($resolved === 'false') {
            $query->unresolved();
        } elseif ($resolved === 'true') {
            $query->resolved();
        }

        if ($request->query('tenant_id')) {
            $query->where('tenant_id', $request->query('tenant_id'));
        }

        $alerts = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => $alerts->items(),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page'    => $alerts->lastPage(),
                'total'        => $alerts->total(),
            ],
        ]);
    }

    /**
     * Mark a system alert as resolved.
     */
    public function resolveAlert(Request $request, SystemAlert $alert): JsonResponse
    {
        if ($alert->resolved_at) {
            return response()->json(['message' => 'La alerta ya estaba resuelta.'], 422);
        }

        /** @var SuperadminUser $admin */
        $admin = $request->user();

        $alert->update([
            'resolved_at'                => now('UTC'),
            'resolved_by_superadmin_id'  => $admin->id,
        ]);

        return response()->json(['message' => 'Alerta marcada como resuelta.', 'data' => $alert]);
    }
}
