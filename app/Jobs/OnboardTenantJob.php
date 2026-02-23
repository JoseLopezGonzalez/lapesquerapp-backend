<?php

namespace App\Jobs;

use App\Models\SystemAlert;
use App\Models\Tenant;
use App\Services\Superadmin\TenantOnboardingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OnboardTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $tenantId
    ) {}

    public function handle(TenantOnboardingService $service): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        Log::info("OnboardTenantJob started for [{$tenant->subdomain}], current step: {$tenant->onboarding_step}");

        $service->run($tenant);

        Log::info("OnboardTenantJob completed for [{$tenant->subdomain}]");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("OnboardTenantJob FAILED for tenant ID {$this->tenantId}: {$exception->getMessage()}");

        $tenant = Tenant::find($this->tenantId);
        if ($tenant && !$tenant->onboarding_failed_at) {
            $tenant->update([
                'onboarding_error'     => "Job agotó reintentos: {$exception->getMessage()}",
                'onboarding_failed_at' => now(),
            ]);
        }

        if ($tenant) {
            SystemAlert::createIfNotExists(
                type: 'onboarding_failed',
                severity: 'critical',
                message: "El onboarding del tenant [{$tenant->subdomain}] ha fallado: {$exception->getMessage()}",
                tenantId: $tenant->id,
                metadata: ['error' => $exception->getMessage(), 'onboarding_step' => $tenant->onboarding_step]
            );
        }
    }
}
