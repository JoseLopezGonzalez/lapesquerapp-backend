<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Tenants base del SaaS para desarrollo.
 *
 * Solo deja un tenant activo apuntando a una base conocida para no romper
 * `tenants:migrate --seed`.
 */
class CentralTenantsSeeder extends Seeder
{
    public function run(): void
    {
        $defaultTenantDatabase = (string) (
            config('database.connections.tenant.database')
            ?: config('database.connections.mysql.database')
            ?: env('DB_DATABASE', 'pesquerapp')
        );

        $tenants = [
            [
                'subdomain' => 'dev',
                'name' => 'Tenant Desarrollo',
                'database' => $defaultTenantDatabase,
                'status' => 'active',
                'plan' => 'basic',
                'timezone' => 'Europe/Madrid',
                'admin_email' => 'dev-admin@pesquerapp.local',
                'onboarding_step' => 8,
            ],
            [
                'subdomain' => 'demo-suspendido',
                'name' => 'Tenant Suspendido Demo',
                'database' => $defaultTenantDatabase . '_suspended',
                'status' => 'suspended',
                'plan' => 'pro',
                'timezone' => 'Europe/Madrid',
                'admin_email' => 'suspended-admin@pesquerapp.local',
                'onboarding_step' => 8,
            ],
            [
                'subdomain' => 'enterprise-preview',
                'name' => 'Tenant Enterprise Preview',
                'database' => $defaultTenantDatabase . '_enterprise',
                'status' => 'pending',
                'plan' => 'enterprise',
                'timezone' => 'Europe/Madrid',
                'admin_email' => 'enterprise-admin@pesquerapp.local',
                'onboarding_step' => 2,
            ],
        ];

        foreach ($tenants as $tenant) {
            Tenant::updateOrCreate(
                ['subdomain' => $tenant['subdomain']],
                [
                    'name' => $tenant['name'],
                    'database' => $tenant['database'],
                    'status' => $tenant['status'],
                    'plan' => $tenant['plan'],
                    'renewal_at' => now()->addMonth()->toDateString(),
                    'timezone' => $tenant['timezone'],
                    'admin_email' => $tenant['admin_email'],
                    'branding_image_url' => null,
                    'last_activity_at' => $tenant['status'] === 'active' ? now() : null,
                    'onboarding_step' => $tenant['onboarding_step'],
                    'onboarding_error' => null,
                    'onboarding_failed_at' => null,
                ]
            );
        }

        $this->command?->info('CentralTenantsSeeder: tenants base de desarrollo sincronizados.');
    }
}
