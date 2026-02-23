<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Models\TenantErrorLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneLogs extends Command
{
    protected $signature   = 'superadmin:prune-logs';
    protected $description = 'Prune old tenant_error_logs (>30 days) and resolved system_alerts (>90 days)';

    public function handle(): void
    {
        $errorLogsCutoff  = now('UTC')->subDays(30);
        $alertsCutoff     = now('UTC')->subDays(90);

        $deletedErrors = TenantErrorLog::where('occurred_at', '<', $errorLogsCutoff)->delete();
        $deletedAlerts = SystemAlert::resolved()->where('resolved_at', '<', $alertsCutoff)->delete();

        Log::info("PruneLogs: deleted {$deletedErrors} error logs and {$deletedAlerts} resolved alerts");

        $this->info("Deleted {$deletedErrors} error logs and {$deletedAlerts} resolved alerts.");
    }
}
