<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOnboardingStuck extends Command
{
    protected $signature   = 'superadmin:check-onboarding-stuck';
    protected $description = 'Detect tenants whose onboarding is stuck (started > 30min ago without failure recorded)';

    public function handle(): void
    {
        $stuckTenants = Tenant::query()
            ->where('status', 'pending')
            ->whereNull('onboarding_failed_at')
            ->where('onboarding_step', '<', 8)
            ->where('created_at', '<=', now('UTC')->subMinutes(30))
            ->get();

        foreach ($stuckTenants as $tenant) {
            SystemAlert::createIfNotExists(
                type: 'onboarding_stuck',
                severity: 'warning',
                message: "El onboarding del tenant [{$tenant->subdomain}] lleva más de 30 minutos sin completarse (paso {$tenant->onboarding_step}/8).",
                tenantId: $tenant->id,
                metadata: [
                    'onboarding_step' => $tenant->onboarding_step,
                    'created_at'      => $tenant->created_at,
                ]
            );

            Log::warning("Onboarding stuck for [{$tenant->subdomain}] at step {$tenant->onboarding_step}");
        }

        $this->info("Checked {$stuckTenants->count()} potentially stuck tenants.");
    }
}
