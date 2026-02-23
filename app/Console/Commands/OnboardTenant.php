<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Superadmin\TenantOnboardingService;
use Illuminate\Console\Command;

class OnboardTenant extends Command
{
    protected $signature = 'tenant:onboard
                            {subdomain : Subdominio del tenant}
                            {admin_email : Email del administrador del tenant}
                            {--name= : Nombre de la empresa}
                            {--plan= : Plan contratado}
                            {--timezone=Europe/Madrid : Zona horaria}';

    protected $description = 'Ejecuta el onboarding completo de un nuevo tenant (crear BD, migraciones, seed, usuario admin, activar)';

    public function handle(TenantOnboardingService $service): int
    {
        $subdomain = $this->argument('subdomain');
        $adminEmail = $this->argument('admin_email');
        $name = $this->option('name') ?: $subdomain;
        $plan = $this->option('plan');
        $timezone = $this->option('timezone');

        $tenant = Tenant::where('subdomain', $subdomain)->first();

        if (!$tenant) {
            $tenant = Tenant::create([
                'name' => $name,
                'subdomain' => $subdomain,
                'database' => 'tenant_' . $subdomain,
                'status' => 'pending',
                'plan' => $plan,
                'timezone' => $timezone,
                'admin_email' => $adminEmail,
                'onboarding_step' => 0,
            ]);

            $this->info("Tenant creado: {$tenant->name} ({$subdomain})");
        } else {
            $this->info("Tenant ya existe: {$tenant->name} (step: {$tenant->onboarding_step})");
        }

        $this->info("Iniciando onboarding...");

        try {
            $service->run($tenant);
            $this->info("Onboarding completado. Tenant activo.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Onboarding falló en step {$tenant->fresh()->onboarding_step}: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
