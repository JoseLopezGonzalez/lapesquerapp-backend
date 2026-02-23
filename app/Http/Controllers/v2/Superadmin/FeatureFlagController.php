<?php

namespace App\Http\Controllers\v2\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Superadmin\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    public function __construct(
        private FeatureFlagService $service
    ) {}

    /**
     * List feature flag defaults for all plans.
     */
    public function planDefaults(): JsonResponse
    {
        $flags = $this->service->getPlanDefaults();

        $formatted = $flags->map(function ($planRows, $flagKey) {
            $row = ['flag_key' => $flagKey, 'description' => $planRows->first()?->description];

            foreach ($planRows as $flag) {
                $row[$flag->plan] = $flag->enabled;
            }

            return $row;
        })->values();

        return response()->json(['data' => $formatted]);
    }

    /**
     * Get effective feature flags for a tenant (plan defaults merged with overrides).
     */
    public function tenantFlags(Tenant $tenant): JsonResponse
    {
        $effective = $this->service->getEffectiveFlags($tenant);

        $overrides = \App\Models\TenantFeatureOverride::where('tenant_id', $tenant->id)
            ->pluck('enabled', 'flag_key')
            ->toArray();

        $formatted = collect($effective)->map(function ($enabled, $flagKey) use ($overrides) {
            return [
                'flag_key'     => $flagKey,
                'enabled'      => $enabled,
                'has_override' => isset($overrides[$flagKey]),
            ];
        })->values();

        return response()->json([
            'tenant' => $tenant->subdomain,
            'plan'   => $tenant->plan ?? 'basic',
            'data'   => $formatted,
        ]);
    }

    /**
     * Set a flag override for a specific tenant.
     */
    public function setOverride(Request $request, Tenant $tenant, string $flag): JsonResponse
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'reason'  => 'nullable|string|max:500',
        ]);

        $override = $this->service->setOverride(
            $tenant,
            $flag,
            $request->boolean('enabled'),
            $request->user(),
            $request->reason
        );

        return response()->json([
            'message' => 'Override guardado.',
            'data'    => $override,
        ]);
    }

    /**
     * Remove a flag override for a specific tenant (revert to plan default).
     */
    public function removeOverride(Tenant $tenant, string $flag): JsonResponse
    {
        $deleted = $this->service->removeOverride($tenant, $flag);

        if (!$deleted) {
            return response()->json(['message' => 'No existía override para este flag.'], 404);
        }

        return response()->json(['message' => 'Override eliminado. El tenant vuelve al valor del plan.']);
    }
}
