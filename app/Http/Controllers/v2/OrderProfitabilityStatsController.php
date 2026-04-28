<?php

namespace App\Http\Controllers\v2;

use App\Exports\v2\OrderProfitabilitySummaryAuditExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\v2\OrderProfitabilityProductsRequest;
use App\Http\Requests\v2\OrderProfitabilitySummaryExportJobRequest;
use App\Http\Requests\v2\OrderProfitabilitySummaryRequest;
use App\Http\Requests\v2\OrderProfitabilityTimelineRequest;
use App\Jobs\GenerateOrderProfitabilitySummaryExport;
use App\Models\Order;
use App\Models\OrderProfitabilityExportJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\v2\OrderProfitabilityStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class OrderProfitabilityStatsController extends Controller
{
    public function summary(OrderProfitabilitySummaryRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $v = $request->validated();

        return response()->json(
            OrderProfitabilityStatsService::getSummary(
                $v['dateFrom'],
                $v['dateTo'],
                $v['productIds'] ?? []
            )
        );
    }

    public function exportSummary(OrderProfitabilitySummaryRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        ini_set('memory_limit', config('exports.operations.profitability_export.memory_limit', '2048M'));
        ini_set('max_execution_time', (string) config('exports.operations.profitability_export.max_execution_time', 1800));

        $v = $request->validated();
        $from = $v['dateFrom'];
        $to = $v['dateTo'];

        return Excel::download(
            new OrderProfitabilitySummaryAuditExport(
                OrderProfitabilityStatsService::getSummaryExportData(
                    $from,
                    $to,
                    $v['productIds'] ?? []
                )
            ),
            "order_profitability_summary_{$from}_{$to}.xlsx",
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function createExportJob(OrderProfitabilitySummaryExportJobRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $tenant = Tenant::where('subdomain', app('currentTenant'))->firstOrFail();
        $v = $request->validated();
        $user = $request->user();

        $exportJob = OrderProfitabilityExportJob::create([
            'uuid' => (string) Str::uuid(),
            'created_by_user_id' => $user instanceof User ? $user->id : null,
            'status' => OrderProfitabilityExportJob::STATUS_PENDING,
            'filters' => [
                'dateFrom' => $v['dateFrom'],
                'dateTo' => $v['dateTo'],
                'productIds' => $v['productIds'] ?? [],
                'onlyMissingCosts' => (bool) ($v['onlyMissingCosts'] ?? false),
            ],
        ]);

        GenerateOrderProfitabilitySummaryExport::dispatch($tenant->id, $exportJob->id);

        return response()->json($this->exportJobPayload($exportJob->refresh()), 202);
    }

    public function showExportJob(string $uuid): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $exportJob = OrderProfitabilityExportJob::where('uuid', $uuid)->firstOrFail();

        return response()->json($this->exportJobPayload($exportJob));
    }

    public function downloadExportJob(string $uuid)
    {
        $this->authorize('viewAny', Order::class);

        $exportJob = OrderProfitabilityExportJob::where('uuid', $uuid)->firstOrFail();

        if (! $exportJob->isFinished() || ! Storage::disk('local')->exists($exportJob->file_path)) {
            return response()->json([
                'message' => 'La exportación todavía no está disponible.',
                'status' => $exportJob->status,
            ], 409);
        }

        return Storage::disk('local')->download($exportJob->file_path, $exportJob->filename);
    }

    public function timeline(OrderProfitabilityTimelineRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $v = $request->validated();

        return response()->json(
            OrderProfitabilityStatsService::getTimeline(
                $v['dateFrom'],
                $v['dateTo'],
                $v['granularity'] ?? 'month',
                $v['productIds'] ?? []
            )
        );
    }

    public function byProduct(OrderProfitabilityProductsRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $v = $request->validated();

        return response()->json(
            OrderProfitabilityStatsService::getByProduct(
                $v['dateFrom'],
                $v['dateTo']
            )
        );
    }

    private function exportJobPayload(OrderProfitabilityExportJob $exportJob): array
    {
        return [
            'id' => $exportJob->uuid,
            'status' => $exportJob->status,
            'filters' => $exportJob->filters,
            'filename' => $exportJob->filename,
            'errorMessage' => $exportJob->error_message,
            'createdAt' => $exportJob->created_at?->toIso8601String(),
            'startedAt' => $exportJob->started_at?->toIso8601String(),
            'finishedAt' => $exportJob->finished_at?->toIso8601String(),
            'downloadUrl' => $exportJob->isFinished()
                ? url("/api/v2/statistics/orders/profitability-summary/export-jobs/{$exportJob->uuid}/download")
                : null,
        ];
    }
}
