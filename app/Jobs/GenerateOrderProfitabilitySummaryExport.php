<?php

namespace App\Jobs;

use App\Exports\v2\OrderProfitabilitySummaryAuditExport;
use App\Models\OrderProfitabilityExportJob;
use App\Models\Tenant;
use App\Services\v2\OrderProfitabilityStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class GenerateOrderProfitabilitySummaryExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public int $tenantId,
        public int $exportJobId
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $this->connectTenant($tenant);

        $exportJob = OrderProfitabilityExportJob::findOrFail($this->exportJobId);
        $exportJob->update([
            'status' => OrderProfitabilityExportJob::STATUS_PROCESSING,
            'started_at' => now('UTC'),
            'error_message' => null,
        ]);

        $filters = $exportJob->filters;
        $from = $filters['dateFrom'];
        $to = $filters['dateTo'];
        $productIds = $filters['productIds'] ?? [];

        ini_set('memory_limit', config('exports.operations.profitability_export.memory_limit', '2048M'));
        ini_set('max_execution_time', (string) config('exports.operations.profitability_export.max_execution_time', 1800));
        config(['excel.cache.driver' => config('exports.excel_cache_driver', 'batch')]);

        $filename = "order_profitability_summary_{$from}_{$to}_{$exportJob->uuid}.xlsx";
        $path = "exports/order-profitability/{$filename}";

        try {
            Excel::store(
                new OrderProfitabilitySummaryAuditExport(
                    OrderProfitabilityStatsService::getSummaryExportData($from, $to, $productIds),
                    (bool) ($filters['onlyMissingCosts'] ?? false)
                ),
                $path,
                'local',
                \Maatwebsite\Excel\Excel::XLSX
            );

            $exportJob->update([
                'status' => OrderProfitabilityExportJob::STATUS_FINISHED,
                'filename' => $filename,
                'file_path' => $path,
                'finished_at' => now('UTC'),
            ]);
        } catch (\Throwable $e) {
            $exportJob->update([
                'status' => OrderProfitabilityExportJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'finished_at' => now('UTC'),
            ]);

            Log::error('Order profitability summary export failed', [
                'tenant_id' => $tenant->id,
                'export_job_id' => $exportJob->id,
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

            OrderProfitabilityExportJob::whereKey($this->exportJobId)->update([
                'status' => OrderProfitabilityExportJob::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now('UTC'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Unable to mark order profitability export as failed', [
                'tenant_id' => $this->tenantId,
                'export_job_id' => $this->exportJobId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
