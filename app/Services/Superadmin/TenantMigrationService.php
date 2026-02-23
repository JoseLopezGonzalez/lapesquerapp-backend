<?php

namespace App\Services\Superadmin;

use App\Jobs\MigrateTenantJob;
use App\Models\Tenant;
use App\Models\TenantMigrationRun;
use App\Models\SuperadminUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantMigrationService
{
    /**
     * Get migration status for a tenant (ran / pending per migration file).
     */
    public function getMigrationStatus(Tenant $tenant): array
    {
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Artisan::call('migrate:status', [
            '--path'     => 'database/migrations/companies',
            '--database' => 'tenant',
        ]);

        $raw = Artisan::output();

        return $this->parseMigrateStatusOutput($raw);
    }

    /**
     * Dispatch a migration job for a single tenant.
     */
    public function runMigrations(Tenant $tenant, ?SuperadminUser $triggeredBy = null): TenantMigrationRun
    {
        $run = TenantMigrationRun::create([
            'tenant_id'                  => $tenant->id,
            'triggered_by_superadmin_id' => $triggeredBy?->id,
            'started_at'                 => now('UTC'),
            'success'                    => false,
        ]);

        MigrateTenantJob::dispatch($tenant->id, $triggeredBy?->id);

        return $run;
    }

    /**
     * Dispatch migration jobs for all active tenants.
     */
    public function runAllMigrations(?SuperadminUser $triggeredBy = null): int
    {
        $tenants = Tenant::active()->get();

        foreach ($tenants as $tenant) {
            MigrateTenantJob::dispatch($tenant->id, $triggeredBy?->id);
        }

        Log::info("MigrateTenantJob dispatched for {$tenants->count()} tenants");

        return $tenants->count();
    }

    /**
     * Paginated run history for a tenant.
     */
    public function getRunHistory(Tenant $tenant, int $perPage = 15): LengthAwarePaginator
    {
        return TenantMigrationRun::where('tenant_id', $tenant->id)
            ->orderByDesc('started_at')
            ->paginate($perPage);
    }

    /**
     * Parse Artisan migrate:status output into a structured array.
     * Handles both table-style output and raw text.
     */
    private function parseMigrateStatusOutput(string $raw): array
    {
        $migrations = [];

        // Split lines, remove ANSI escape codes
        $lines = explode("\n", preg_replace('/\x1B\[[0-9;]*[mK]/', '', $raw));

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || str_starts_with($line, '+') || str_starts_with($line, '|  Migration')) {
                continue;
            }

            // Table format: | Ran? | Migration | Batch |
            if (str_starts_with($line, '|')) {
                $parts = array_map('trim', explode('|', $line));
                $parts = array_filter($parts, fn ($p) => $p !== '');
                $parts = array_values($parts);

                if (count($parts) >= 2) {
                    $ran   = strtolower($parts[0] ?? '') === 'yes' || str_contains(strtolower($parts[0] ?? ''), 'ran');
                    $name  = $parts[1] ?? '';
                    $batch = isset($parts[2]) && is_numeric($parts[2]) ? (int) $parts[2] : null;

                    if ($name) {
                        $migrations[] = [
                            'migration' => $name,
                            'ran'       => $ran,
                            'batch'     => $batch,
                        ];
                    }
                }

                continue;
            }

            // Plain text format: "  [..] migration_name"
            if (preg_match('/\[(Ran|Pending)\]\s+(.+)/i', $line, $m)) {
                $migrations[] = [
                    'migration' => trim($m[2]),
                    'ran'       => strtolower($m[1]) === 'ran',
                    'batch'     => null,
                ];
            }
        }

        $pending = count(array_filter($migrations, fn ($m) => !$m['ran']));
        $ran     = count(array_filter($migrations, fn ($m) => $m['ran']));

        return [
            'migrations'     => $migrations,
            'total'          => count($migrations),
            'ran'            => $ran,
            'pending'        => $pending,
            'raw_output'     => $raw,
        ];
    }
}
