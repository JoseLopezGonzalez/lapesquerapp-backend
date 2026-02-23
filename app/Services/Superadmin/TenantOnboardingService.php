<?php

namespace App\Services\Superadmin;

use App\Enums\Role;
use App\Mail\TenantWelcomeEmail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class TenantOnboardingService
{
    public const TOTAL_STEPS = 8;

    private const STEP_LABELS = [
        0 => 'Pendiente',
        1 => 'Registro de tenant',
        2 => 'Creación de base de datos',
        3 => 'Migraciones',
        4 => 'Datos iniciales (seeder)',
        5 => 'Usuario administrador',
        6 => 'Configuración inicial',
        7 => 'Activación',
        8 => 'Email de bienvenida',
    ];

    private const STEPS = [
        1 => 'stepCreateTenantRecord',
        2 => 'stepCreateDatabase',
        3 => 'stepRunMigrations',
        4 => 'stepRunSeeder',
        5 => 'stepCreateAdminUser',
        6 => 'stepSaveSettings',
        7 => 'stepActivate',
        8 => 'stepSendWelcomeEmail',
    ];

    public static function stepLabel(int $step): string
    {
        return self::STEP_LABELS[$step] ?? "Paso {$step}";
    }

    /**
     * Run the full onboarding pipeline for a tenant.
     * Resumes from onboarding_step + 1 (idempotent).
     * Persists error info on the tenant record if a step fails.
     */
    public function run(Tenant $tenant): void
    {
        $startFrom = ($tenant->onboarding_step ?? 0) + 1;

        $tenant->update([
            'onboarding_error' => null,
            'onboarding_failed_at' => null,
        ]);

        foreach (self::STEPS as $stepNumber => $method) {
            if ($stepNumber < $startFrom) {
                continue;
            }

            $label = self::stepLabel($stepNumber);
            Log::info("Onboarding [{$tenant->subdomain}] step {$stepNumber}/{$label}: {$method}");

            try {
                $this->{$method}($tenant);
                $tenant->update(['onboarding_step' => $stepNumber]);
            } catch (\Throwable $e) {
                $errorMsg = "Paso {$stepNumber} ({$label}): {$e->getMessage()}";

                Log::error("Onboarding [{$tenant->subdomain}] failed at step {$stepNumber}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $tenant->update([
                    'onboarding_error' => $errorMsg,
                    'onboarding_failed_at' => now(),
                ]);

                throw $e;
            }
        }
    }

    /** Step 1: Tenant record already exists (created by store endpoint). */
    protected function stepCreateTenantRecord(Tenant $tenant): void
    {
        // No-op: tenant row created before dispatching the job.
    }

    /** Step 2: Create the tenant's MySQL database. */
    protected function stepCreateDatabase(Tenant $tenant): void
    {
        $dbName = $tenant->database;
        $charset = 'utf8mb4';
        $collation = 'utf8mb4_unicode_ci';

        $exists = DB::connection('mysql')
            ->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);

        if (!empty($exists)) {
            Log::info("Onboarding [{$tenant->subdomain}] DB {$dbName} already exists, skipping.");
            return;
        }

        DB::connection('mysql')
            ->statement("CREATE DATABASE `{$dbName}` CHARACTER SET {$charset} COLLATE {$collation}");
    }

    /** Step 3: Run all tenant migrations. */
    protected function stepRunMigrations(Tenant $tenant): void
    {
        $this->connectToTenantDb($tenant);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/companies',
            '--database' => 'tenant',
            '--force' => true,
        ]);

        Log::info("Onboarding [{$tenant->subdomain}] migrations output: " . Artisan::output());
    }

    /** Step 4: Run the production seeder (catalogs only). */
    protected function stepRunSeeder(Tenant $tenant): void
    {
        $this->connectToTenantDb($tenant);

        config(['database.default' => 'tenant']);

        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'Database\\Seeders\\TenantProductionSeeder',
            '--force' => true,
        ]);

        Log::info("Onboarding [{$tenant->subdomain}] seeder output: " . Artisan::output());
    }

    /** Step 5: Create the admin user in the tenant DB. */
    protected function stepCreateAdminUser(Tenant $tenant): void
    {
        $this->connectToTenantDb($tenant);

        $email = $tenant->admin_email;
        if (!$email) {
            Log::warning("Onboarding [{$tenant->subdomain}] no admin_email, skipping user creation.");
            return;
        }

        $existing = User::on('tenant')->where('email', $email)->first();
        if ($existing) {
            Log::info("Onboarding [{$tenant->subdomain}] admin user {$email} already exists.");
            return;
        }

        $user = new User();
        $user->setConnection('tenant');
        $user->name = explode('@', $email)[0];
        $user->email = $email;
        $user->role = Role::Administrador->value;
        $user->save();

        Log::info("Onboarding [{$tenant->subdomain}] admin user created: {$email}");
    }

    /** Step 6: Sync central tenant info into the tenant's settings table. */
    protected function stepSaveSettings(Tenant $tenant): void
    {
        $this->connectToTenantDb($tenant);

        $settingsFromCentral = [
            'company.display_name' => $tenant->name,
            'company.logo_url' => $tenant->branding_image_url ?? '',
        ];

        foreach ($settingsFromCentral as $key => $value) {
            DB::connection('tenant')->table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value ?? '']
            );
        }
    }

    /** Step 7: Activate the tenant. */
    protected function stepActivate(Tenant $tenant): void
    {
        $tenant->update(['status' => 'active']);

        app(TenantManagementService::class)->invalidateCorsCache($tenant->subdomain);
    }

    /** Step 8: Send welcome email to the tenant admin. */
    protected function stepSendWelcomeEmail(Tenant $tenant): void
    {
        if (!$tenant->admin_email) {
            return;
        }

        $tenantUrl = 'https://' . $tenant->subdomain . '.lapesquerapp.es';

        Mail::to($tenant->admin_email)->send(
            new TenantWelcomeEmail($tenant->name, $tenantUrl, $tenant->admin_email)
        );
    }

    private function connectToTenantDb(Tenant $tenant): void
    {
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }
}
