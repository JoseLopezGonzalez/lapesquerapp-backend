<?php

namespace App\Jobs;

use App\Models\OrderProfitabilityStatJob;
use App\Models\Tenant;
use App\Services\v2\OrderProfitabilityStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateOrderProfitabilityStat implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public int $tenantId,
        public int $statJobId
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $this->connectTenant($tenant);

        $statJob = OrderProfitabilityStatJob::findOrFail($this->statJobId);
        $statJob->update([
            'status' => OrderProfitabilityStatJob::STATUS_PROCESSING,
            'started_at' => now('UTC'),
            'error_message' => null,
        ]);

        $filters = $statJob->filters;
        $from = $filters['dateFrom'];
        $to = $filters['dateTo'];
        $productIds = $filters['productIds'] ?? [];

        ini_set('memory_limit', config('exports.operations.profitability_export.memory_limit', '2048M'));
        ini_set('max_execution_time', (string) config('exports.operations.profitability_export.max_execution_time', 1800));

        try {
            $result = match ($statJob->type) {
                OrderProfitabilityStatJob::TYPE_PRODUCTS => OrderProfitabilityStatsService::getByProduct($from, $to),
                default => OrderProfitabilityStatsService::getSummary($from, $to, $productIds),
            };

            $statJob->update([
                'status' => OrderProfitabilityStatJob::STATUS_FINISHED,
                'result' => $result,
                'finished_at' => now('UTC'),
            ]);
        } catch (\Throwable $e) {
            $statJob->update([
                'status' => OrderProfitabilityStatJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'finished_at' => now('UTC'),
            ]);

            Log::error('Order profitability stat job failed', [
                'tenant_id' => $tenant->id,
                'stat_job_id' => $statJob->id,
                'type' => $statJob->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function connectTenant(Tenant $tenant): void
    {
        app()->instance('currentTenant', $tenant->subdomain);

        config([
            'database.default' => 'tenant',
            'database.connections.tenant.database' => $tenant->database,
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    public function failed(\Throwable $exception): void
    {
        try {
            $tenant = Tenant::find($this->tenantId);
            if (! $tenant) {
                return;
            }

            $this->connectTenant($tenant);

            OrderProfitabilityStatJob::whereKey($this->statJobId)->update([
                'status' => OrderProfitabilityStatJob::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now('UTC'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Unable to mark order profitability stat job as failed', [
                'tenant_id' => $this->tenantId,
                'stat_job_id' => $this->statJobId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
