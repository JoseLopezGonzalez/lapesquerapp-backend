<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use Illuminate\Database\Seeder;

/**
 * Habilita todos los feature flags para el tenant de desarrollo (`dev`).
 *
 * Pensado solo para entorno local/dev. Es idempotente.
 */
class DevTenantFeatureOverridesSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('subdomain', 'dev')->first();

        if (!$tenant) {
            $this->command?->warn('DevTenantFeatureOverridesSeeder: tenant dev no encontrado en tabla tenants.');

            return;
        }

        // Usar todos los flags definidos (independiente del plan)
        $flagKeys = FeatureFlag::query()
            ->select('flag_key')
            ->distinct()
            ->pluck('flag_key');

        foreach ($flagKeys as $flagKey) {
            TenantFeatureOverride::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'flag_key' => $flagKey,
                ],
                [
                    'enabled' => true,
                    'reason' => 'Dev tenant: all features enabled',
                ]
            );
        }

        $this->command?->info("DevTenantFeatureOverridesSeeder: habilitados " . $flagKeys->count() . " flags para tenant dev.");
    }
}
