<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DetectSuspiciousActivity extends Command
{
    protected $signature   = 'superadmin:detect-suspicious';
    protected $description = 'Detect suspicious login activity and queue health issues across tenants';

    private const FAILED_ATTEMPTS_PER_IP    = 10;
    private const FAILED_ATTEMPTS_PER_EMAIL = 5;
    private const QUEUE_STOPPED_MINUTES     = 10;

    public function handle(): void
    {
        $this->checkQueueHealth();
        $this->checkTenantLoginAttempts();
    }

    private function checkQueueHealth(): void
    {
        try {
            $pendingJobs = Redis::connection()->llen('queues:default');
            $failedJobs  = DB::table('failed_jobs')->count();

            if ($pendingJobs === null) {
                SystemAlert::createIfNotExists(
                    type: 'queue_stopped',
                    severity: 'critical',
                    message: 'La cola de trabajos parece estar detenida (no se puede leer Redis).',
                    metadata: ['failed_jobs' => $failedJobs]
                );
            }

            Log::info("Queue health check: pending={$pendingJobs}, failed={$failedJobs}");
        } catch (\Throwable $e) {
            Log::warning("Queue health check failed: {$e->getMessage()}");
        }
    }

    private function checkTenantLoginAttempts(): void
    {
        $tenants = Tenant::active()->get();

        foreach ($tenants as $tenant) {
            try {
                config(['database.connections.tenant.database' => $tenant->database]);
                DB::purge('tenant');
                DB::reconnect('tenant');

                $since = now('UTC')->subHour();

                // Check failed attempts per IP
                $suspiciousIps = DB::connection('tenant')
                    ->table('login_attempts')
                    ->select('ip_address', DB::raw('COUNT(*) as attempts'))
                    ->where('success', false)
                    ->where('attempted_at', '>=', $since)
                    ->whereNotNull('ip_address')
                    ->groupBy('ip_address')
                    ->having('attempts', '>=', self::FAILED_ATTEMPTS_PER_IP)
                    ->get();

                foreach ($suspiciousIps as $row) {
                    SystemAlert::createIfNotExists(
                        type: 'suspicious_activity',
                        severity: 'warning',
                        message: "IP {$row->ip_address} tiene {$row->attempts} intentos fallidos en la última hora en tenant {$tenant->subdomain}.",
                        tenantId: $tenant->id,
                        metadata: ['ip' => $row->ip_address, 'attempts' => $row->attempts]
                    );
                }

                // Check failed attempts per email
                $suspiciousEmails = DB::connection('tenant')
                    ->table('login_attempts')
                    ->select('email', DB::raw('COUNT(*) as attempts'))
                    ->where('success', false)
                    ->where('attempted_at', '>=', $since)
                    ->groupBy('email')
                    ->having('attempts', '>=', self::FAILED_ATTEMPTS_PER_EMAIL)
                    ->get();

                foreach ($suspiciousEmails as $row) {
                    SystemAlert::createIfNotExists(
                        type: 'suspicious_activity',
                        severity: 'warning',
                        message: "Email {$row->email} tiene {$row->attempts} intentos fallidos en la última hora en tenant {$tenant->subdomain}.",
                        tenantId: $tenant->id,
                        metadata: ['email' => $row->email, 'attempts' => $row->attempts]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("DetectSuspiciousActivity: error checking tenant [{$tenant->subdomain}]: {$e->getMessage()}");
            }
        }
    }
}
