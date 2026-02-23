<?php

namespace App\Services\Superadmin;

use App\Models\ImpersonationLog;
use App\Models\SystemAlert;
use App\Models\Tenant;
use App\Models\TenantErrorLog;
use App\Models\TenantMigrationRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ObservabilityService
{
    /**
     * Error logs for a specific tenant (last 30 days, paginated).
     */
    public function getTenantErrorLogs(Tenant $tenant, int $perPage = 20, int $days = 30): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return TenantErrorLog::where('tenant_id', $tenant->id)
            ->recent($days)
            ->orderByDesc('occurred_at')
            ->paginate($perPage);
    }

    /**
     * Global error logs grouped by tenant (last 30 days).
     */
    public function getGlobalErrorLogs(int $perPage = 50, int $days = 30): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return TenantErrorLog::with('tenant:id,subdomain,name')
            ->recent($days)
            ->orderByDesc('occurred_at')
            ->paginate($perPage);
    }

    /**
     * Recent activity feed aggregating: impersonation, migrations, alerts, tenant status changes.
     */
    public function getActivityFeed(int $limit = 50): Collection
    {
        $items = collect();

        // Impersonation sessions
        ImpersonationLog::with(['superadminUser:id,name', 'tenant:id,subdomain'])
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->each(function ($log) use (&$items) {
                $items->push([
                    'type'      => 'impersonation',
                    'icon'      => 'user-switch',
                    'severity'  => 'info',
                    'message'   => "Impersonación {$log->mode} de {$log->superadminUser?->name} en {$log->tenant?->subdomain}" . ($log->reason ? " — {$log->reason}" : ''),
                    'tenant'    => $log->tenant?->subdomain,
                    'tenant_id' => $log->tenant_id,
                    'at'        => $log->started_at,
                ]);
            });

        // Migration runs
        TenantMigrationRun::with(['tenant:id,subdomain', 'superadminUser:id,name'])
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->each(function ($run) use (&$items) {
                $items->push([
                    'type'      => 'migration',
                    'icon'      => 'database',
                    'severity'  => $run->success ? 'info' : 'warning',
                    'message'   => "Migración tenant {$run->tenant?->subdomain}: " . ($run->success ? "{$run->migrations_applied} aplicadas" : 'FALLÓ'),
                    'tenant'    => $run->tenant?->subdomain,
                    'tenant_id' => $run->tenant_id,
                    'at'        => $run->started_at,
                ]);
            });

        // System alerts
        SystemAlert::with('tenant:id,subdomain')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->each(function ($alert) use (&$items) {
                $items->push([
                    'type'      => 'alert',
                    'icon'      => 'alert-triangle',
                    'severity'  => $alert->severity,
                    'message'   => $alert->message,
                    'tenant'    => $alert->tenant?->subdomain,
                    'tenant_id' => $alert->tenant_id,
                    'at'        => $alert->created_at,
                    'resolved'  => $alert->resolved_at !== null,
                ]);
            });

        // Tenant status changes (tenants activated/suspended recently)
        Tenant::whereNotNull('updated_at')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->each(function ($tenant) use (&$items) {
                $items->push([
                    'type'      => 'tenant_status',
                    'icon'      => 'building',
                    'severity'  => 'info',
                    'message'   => "Tenant {$tenant->subdomain} — estado: {$tenant->status}",
                    'tenant'    => $tenant->subdomain,
                    'tenant_id' => $tenant->id,
                    'at'        => $tenant->updated_at,
                ]);
            });

        return $items
            ->sortByDesc('at')
            ->values()
            ->take($limit);
    }

    /**
     * Queue health metrics.
     */
    public function getQueueHealth(): array
    {
        $pendingJobs = 0;
        $redisStatus = 'unknown';

        try {
            $pendingJobs = (int) Redis::connection()->llen('queues:default');
            $redisStatus = 'ok';
        } catch (\Throwable $e) {
            $redisStatus = 'error: ' . $e->getMessage();
        }

        $failedJobs = DB::table('failed_jobs')->count();

        return [
            'pending_jobs' => $pendingJobs,
            'failed_jobs'  => $failedJobs,
            'redis_status' => $redisStatus,
            'healthy'      => $redisStatus === 'ok',
        ];
    }
}
