<?php

namespace App\Services\Production;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\Production;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductionControlPanelService
{
    public function __construct(
        private ProductionClosureService $closureService,
        private ProductionCostResolver $costResolver,
    ) {}

    // ========================================================
    // ENTRY POINT
    // ========================================================

    public function getPanelData(array $filters): array
    {
        $query   = $this->buildBaseQuery($filters);
        $perPage = (int) ($filters['per_page'] ?? 25);

        $paginator = $this->buildProductionList($query, $perPage);

        return [
            'summary'     => $this->buildSummary(),
            'productions' => collect($paginator->items())->all(),
            'pagination'  => [
                'currentPage' => $paginator->currentPage(),
                'perPage'     => $paginator->perPage(),
                'total'       => $paginator->total(),
                'lastPage'    => $paginator->lastPage(),
            ],
        ];
    }

    // ========================================================
    // SUMMARY (cheap aggregate queries only)
    // ========================================================

    private function buildSummary(): array
    {
        return [
            'openProductions'  => Production::whereNotNull('opened_at')->whereNull('closed_at')->count(),
            'closedProductions' => Production::whereNotNull('closed_at')->count(),
            'boxesWithoutCost' => $this->countBoxesWithoutKnownCost(),
        ];
    }

    // ========================================================
    // PRODUCTION LIST
    // ========================================================

    private function buildBaseQuery(array $filters): Builder
    {
        $query = Production::query()->with(['species']);

        if (!empty($filters['lot'])) {
            $query->where('lot', 'like', '%' . $filters['lot'] . '%');
        }

        if (!empty($filters['species_id'])) {
            $query->where('species_id', $filters['species_id']);
        }

        if (!empty($filters['status'])) {
            match ($filters['status']) {
                'open'   => $query->whereNotNull('opened_at')->whereNull('closed_at'),
                'closed' => $query->whereNotNull('closed_at'),
                default  => null,
            };
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $sortBy  = \in_array($filters['sort_by'] ?? '', ['date', 'lot', 'id']) ? $filters['sort_by'] : 'id';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query;
    }

    private function buildProductionList(Builder $query, int $perPage): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage);

        $rows = collect($paginator->items())
            ->map(fn (Production $production) => $this->buildProductionRow($production))
            ->all();

        $paginator->setCollection(collect($rows));

        return $paginator;
    }

    // ========================================================
    // PER-PRODUCTION ROW
    // ========================================================

    private function buildProductionRow(Production $production): array
    {
        $reconciliation = $production->getDetailedReconciliationByProduct();
        $closure        = $this->closureService->canClose($production);
        $costStatus     = $this->getProductionCostStatus($production);

        return [
            'id'      => $production->id,
            'lot'     => $production->lot,
            'date'    => $production->date?->format('Y-m-d'),
            'status'  => $this->resolveStatus($production, $reconciliation, $closure),
            'species' => $production->species
                ? ['id' => $production->species->id, 'name' => $production->species->name]
                : null,
            'metrics' => [
                'inputWeightKg'       => round($this->getStockInputWeight($production), 3),
                'producedWeightKg'    => round($closure['summary']['producedWeight'] ?? 0, 3),
                'salesWeightKg'       => round($closure['summary']['salesWeight'] ?? 0, 3),
                'stockWeightKg'       => round($closure['summary']['stockWeight'] ?? 0, 3),
                'reprocessedWeightKg' => round($closure['summary']['reprocessedWeight'] ?? 0, 3),
                'balanceWeightKg'     => round($closure['summary']['balanceWeight'] ?? 0, 3),
            ],
            'reconciliation' => [
                'status'          => $reconciliation['summary']['overallStatus'] ?? 'ok',
                'productsOk'      => $reconciliation['summary']['productsOk'] ?? 0,
                'productsWarning' => $reconciliation['summary']['productsWarning'] ?? 0,
                'productsError'   => $reconciliation['summary']['productsError'] ?? 0,
            ],
            'closure' => [
                'canClose'        => $closure['canClose'],
                'blockingReasons' => array_column($closure['blockingReasons'], 'code'),
            ],
            'costs'  => $costStatus,
            'alerts' => $this->buildAlerts($reconciliation, $closure, $costStatus),
        ];
    }

    // ========================================================
    // STATUS & ALERTS
    // ========================================================

    private function resolveStatus(Production $production, array $reconciliation, array $closure): string
    {
        // Priority: closed > not_reconciled > ready_to_close > not_closeable > open
        if ($production->isClosed()) {
            return 'closed';
        }

        $overallStatus = $reconciliation['summary']['overallStatus'] ?? 'ok';
        if (\in_array($overallStatus, ['warning', 'error'])) {
            return 'not_reconciled';
        }

        if ($closure['canClose']) {
            return 'ready_to_close';
        }

        if (!empty($closure['blockingReasons'])) {
            return 'not_closeable';
        }

        return 'open';
    }

    private function buildAlerts(array $reconciliation, array $closure, array $costStatus): array
    {
        $alerts = [];

        $overallStatus = $reconciliation['summary']['overallStatus'] ?? 'ok';
        if ($overallStatus !== 'ok') {
            $balance  = (float) ($reconciliation['summary']['totalBalanceWeight'] ?? 0);
            $alerts[] = [
                'severity' => $overallStatus === 'error' ? 'critical' : 'warning',
                'code'     => 'reconciliation_not_ok',
                'message'  => $balance < 0
                    ? 'Hay ' . abs(round($balance, 3)) . ' kg mas contabilizados que producidos.'
                    : 'Faltan ' . abs(round($balance, 3)) . ' kg por contabilizar.',
            ];
        }

        foreach ($closure['blockingReasons'] as $reason) {
            if ($reason['code'] === 'reconciliation_not_ok') {
                continue;
            }
            $alerts[] = [
                'severity' => 'warning',
                'code'     => $reason['code'],
                'message'  => $reason['message'],
                'action'   => $reason['action'] ?? null,
            ];
        }

        if ($costStatus['hasMissingCosts']) {
            $alerts[] = [
                'severity' => 'info',
                'code'     => 'missing_cost',
                'message'  => "Hay {$costStatus['missingCostBoxesCount']} cajas ({$costStatus['missingCostWeightKg']} kg) sin coste trazable conocido.",
            ];
        }

        return $alerts;
    }

    // ========================================================
    // COST STATUS
    // V1: approximation — boxes without manual cost and without reception.
    // Full accuracy requires ProductionCostResolver per box.
    // ========================================================

    private function getProductionCostStatus(Production $production): array
    {
        $base = Box::query()
            ->where('lot', $production->lot)
            ->whereDoesntHave('productionInputs');

        ['count' => $count, 'weight' => $weight] = $this->countMissingCosts($base);

        return [
            'hasMissingCosts'       => $count > 0,
            'missingCostBoxesCount' => $count,
            'missingCostWeightKg'   => round($weight, 3),
        ];
    }

    // ========================================================
    // HELPERS
    // ========================================================

    private function getStockInputWeight(Production $production): float
    {
        return (float) $production->allInputs()
            ->join('boxes', 'production_inputs.box_id', '=', 'boxes.id')
            ->sum('boxes.net_weight');
    }

    private function countBoxesWithoutKnownCost(): int
    {
        $base = Box::query()
            ->whereDoesntHave('productionInputs')
            ->whereHas('palletBox.pallet', fn ($q) =>
                $q->whereIn('status', [Pallet::STATE_REGISTERED, Pallet::STATE_STORED])
            );

        return $this->countMissingCosts($base)['count'];
    }

    private function countMissingCosts(Builder $query): array
    {
        $count = 0;
        $weight = 0.0;

        $query
            ->select(['id', 'net_weight', 'article_id', 'lot', 'manual_cost_per_kg'])
            ->orderBy('id')
            ->chunkById(500, function ($boxes) use (&$count, &$weight) {
                foreach ($boxes as $box) {
                    if ($this->costResolver->getBoxCostPerKg($box) !== null) {
                        continue;
                    }

                    $count++;
                    $weight += (float) $box->net_weight;
                }
            });

        return ['count' => $count, 'weight' => $weight];
    }
}
