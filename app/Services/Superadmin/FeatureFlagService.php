<?php

namespace App\Services\Superadmin;

use App\Models\FeatureFlag;
use App\Models\SuperadminUser;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FeatureFlagService
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Get the effective feature flags for a tenant.
     * Merges plan defaults with per-tenant overrides.
     *
     * @return array<string, bool>
     */
    public function getEffectiveFlags(Tenant $tenant): array
    {
        $cacheKey = "feature_flags:tenant:{$tenant->id}:{$tenant->plan}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenant) {
            $planFlags = FeatureFlag::where('plan', $tenant->plan ?? 'basic')
                ->pluck('enabled', 'flag_key')
                ->toArray();

            $overrides = TenantFeatureOverride::where('tenant_id', $tenant->id)
                ->pluck('enabled', 'flag_key')
                ->toArray();

            return array_merge($planFlags, $overrides);
        });
    }

    /**
     * Check if a specific flag is enabled for a tenant.
     */
    public function isEnabled(Tenant $tenant, string $flagKey): bool
    {
        $flags = $this->getEffectiveFlags($tenant);

        return (bool) ($flags[$flagKey] ?? false);
    }

    /**
     * Set or update a per-tenant flag override.
     */
    public function setOverride(
        Tenant $tenant,
        string $flagKey,
        bool $enabled,
        ?SuperadminUser $superadmin = null,
        ?string $reason = null
    ): TenantFeatureOverride {
        $override = TenantFeatureOverride::updateOrCreate(
            ['tenant_id' => $tenant->id, 'flag_key' => $flagKey],
            [
                'enabled'                    => $enabled,
                'overridden_by_superadmin_id' => $superadmin?->id,
                'reason'                     => $reason,
            ]
        );

        $this->invalidateCache($tenant);

        return $override;
    }

    /**
     * Remove a per-tenant flag override (revert to plan default).
     */
    public function removeOverride(Tenant $tenant, string $flagKey): bool
    {
        $deleted = TenantFeatureOverride::where('tenant_id', $tenant->id)
            ->where('flag_key', $flagKey)
            ->delete();

        $this->invalidateCache($tenant);

        return $deleted > 0;
    }

    /**
     * Get default flags for all plans.
     *
     * @return Collection
     */
    public function getPlanDefaults(): Collection
    {
        return FeatureFlag::orderBy('flag_key')->get()->groupBy('flag_key');
    }

    private function invalidateCache(Tenant $tenant): void
    {
        Cache::forget("feature_flags:tenant:{$tenant->id}:{$tenant->plan}");
    }
}
