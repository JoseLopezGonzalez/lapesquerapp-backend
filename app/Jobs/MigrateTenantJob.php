<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\TenantMigrationRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function __construct(
        public int $tenantId,
        public ?int $triggeredBySupeadminId = null
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        $run = TenantMigrationRun::create([
            'tenant_id'                  => $tenant->id,
            'triggered_by_superadmin_id' => $this->triggeredBySupeadminId,
            'started_at'                 => now('UTC'),
            'success'                    => false,
        ]);

        Log::info("MigrateTenantJob started for [{$tenant->subdomain}]");

        try {
            config(['database.connections.tenant.database' => $tenant->database]);
            DB::purge('tenant');
            DB::reconnect('tenant');
            config(['database.default' => 'tenant']);

            Artisan::call('migrate', [
                '--path'     => 'database/migrations/companies',
                '--database' => 'tenant',
                '--force'    => true,
            ]);

            $output = Artisan::output();

            $migrationsApplied = substr_count($output, 'DONE');

            $run->update([
                'migrations_applied' => $migrationsApplied,
                'output'             => $output,
                'success'            => true,
                'finished_at'        => now('UTC'),
            ]);

            Log::info("MigrateTenantJob completed for [{$tenant->subdomain}]: {$migrationsApplied} migrations applied");
        } catch (\Throwable $e) {
            $run->update([
                'output'      => $e->getMessage(),
                'success'     => false,
                'finished_at' => now('UTC'),
            ]);

            Log::error("MigrateTenantJob FAILED for [{$tenant->subdomain}]: {$e->getMessage()}");

            throw $e;
        }
    }
}
