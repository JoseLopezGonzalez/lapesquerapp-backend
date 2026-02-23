<?php

namespace App\Http\Controllers\v2\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Superadmin\TenantMigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantMigrationController extends Controller
{
    public function __construct(
        private TenantMigrationService $service
    ) {}

    /**
     * Get migration status (ran/pending) for a tenant.
     */
    public function status(Tenant $tenant): JsonResponse
    {
        try {
            $status = $this->service->getMigrationStatus($tenant);

            return response()->json(['data' => $status]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener el estado de migraciones.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dispatch migration job for a specific tenant.
     */
    public function run(Request $request, Tenant $tenant): JsonResponse
    {
        $run = $this->service->runMigrations($tenant, $request->user());

        return response()->json([
            'message' => 'Migraciones encoladas para el tenant.',
            'run_id'  => $run->id,
            'tenant'  => $tenant->subdomain,
        ]);
    }

    /**
     * Get migration run history for a tenant.
     */
    public function history(Request $request, Tenant $tenant): JsonResponse
    {
        $history = $this->service->getRunHistory(
            $tenant,
            (int) $request->query('per_page', 15)
        );

        return response()->json([
            'data' => $history->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
                'total'        => $history->total(),
            ],
        ]);
    }

    /**
     * Dispatch migration jobs for all active tenants.
     */
    public function runAll(Request $request): JsonResponse
    {
        $count = $this->service->runAllMigrations($request->user());

        return response()->json([
            'message'         => 'Migraciones encoladas para todos los tenants activos.',
            'tenants_queued'  => $count,
        ]);
    }
}
