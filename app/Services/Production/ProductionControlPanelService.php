<?php

namespace App\Services\Production;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Production;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductionControlPanelService
{
    public function __construct(
        private ProductionClosureService $closureService,
    ) {}

    // ========================================================
    // ENTRY POINT
    // ========================================================

    public function getPanelData(array $filters): array
    {
        $query  = $this->buildBaseQuery($filters);
        $perPage = (int) ($filters['per_page'] ?? 25);

        $summary      = $this->buildSummary();
        $globalAlerts = $this->buildGlobalAlerts();
        $paginator    = $this->buildProductionList($query, $perPage);

        return [
            'summary'     => $summary,
            'alerts'      => $globalAlerts,
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
        $open   = Production::whereNotNull('opened_at')->whereNull('closed_at')->count();
        $closed = Production::whereNotNull('closed_at')->count();

        $lotsWithoutProduction = $this->getLotsInStockWithoutProduction();
        $lotsCount  = count($lotsWithoutProduction);
        $boxesCount = (int) array_sum(array_column($lotsWithoutProduction, 'boxesCount'));

        $boxesWithoutCost = $this->countBoxesWithoutKnownCost();

        return [
            'openProductions'                        => $open,
            'closedProductions'                      => $closed,
            'lotsInStockWithoutProduction'           => $lotsCount,
            'lotsInStockWithoutProductionBoxesCount' => $boxesCount,
            'boxesWithoutCost'                       => $boxesWithoutCost,
        ];
    }

    // ========================================================
    // GLOBAL ALERTS (lots in stock with no production/reception)
    // ========================================================

    private function buildGlobalAlerts(): array
    {
        return array_map(fn (array $entry) => [
            'severity'   => 'critical',
            'code'       => 'stock_without_production',
            'title'      => 'Stock sin produccion ni recepcion',
            'message'    => "El lote {$entry['lot']} tiene {$entry['weightKg']} kg en stock sin produccion ni recepcion asociada.",
            'lot'        => $entry['lot'],
            'product'    => $entry['product'],
            'weightKg'   => $entry['weightKg'],
            'boxesCount' => $entry['boxesCount'],
            'actions'    => [
                ['type' => 'open_lot_search', 'label' => 'Ver cajas'],
            ],
        ], $this->getLotsInStockWithoutProduction());
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

        $sortBy  = in_array($filters['sort_by'] ?? '', ['date', 'lot', 'id']) ? $filters['sort_by'] : 'id';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query;
    }

    private function buildProductionList(Builder $query, int $perPage): LengthAwarePaginator
    {
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
            'date'    => $production->date?->toDateString(),
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
        if (in_array($overallStatus, ['warning', 'error'])) {
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

        // Reconciliation alert (deduplicates the reconciliation_not_ok blocking reason)
        $overallStatus = $reconciliation['summary']['overallStatus'] ?? 'ok';
        if ($overallStatus !== 'ok') {
            $balance = (float) ($reconciliation['summary']['totalBalanceWeight'] ?? 0);
            $alerts[] = [
                'severity' => $overallStatus === 'error' ? 'critical' : 'warning',
                'code'     => 'reconciliation_not_ok',
                'message'  => $balance < 0
                    ? 'Hay ' . abs(round($balance, 3)) . ' kg mas contabilizados que producidos.'
                    : 'Faltan ' . abs(round($balance, 3)) . ' kg por contabilizar.',
            ];
        }

        // Closure blocking reasons (skip reconciliation_not_ok, already covered above)
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

        // Cost alert (informative, does not block closure)
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
    // COST STATUS (V1: approximation based on manual_cost_per_kg + reception)
    // Full accuracy requires ProductionCostResolver per box, which is too slow for a list.
    // ========================================================

    private function getProductionCostStatus(Production $production): array
    {
        // A box is "potentially without cost" when:
        // - manual_cost_per_kg IS NULL (no manual override)
        // - its pallet has no reception_id (no traceable cost from reception)
        // - it is not used as input in another production (not reprocessed)
        // Over-counts if the production has final outputs with computable cost via ProductionCostResolver,
        // but is a valid V1 indicator. Full cost resolution belongs to the regularization module.
        $query = Box::where('lot', $production->lot)
            ->whereNull('manual_cost_per_kg')
            ->whereDoesntHave('productionInputs')
            ->whereHas('palletBox.pallet', fn ($q) => $q->whereNull('reception_id'));

        $count  = $query->count();
        $weight = (float) Box::where('lot', $production->lot)
            ->whereNull('manual_cost_per_kg')
            ->whereDoesntHave('productionInputs')
            ->whereHas('palletBox.pallet', fn ($q) => $q->whereNull('reception_id'))
            ->sum('net_weight');

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

    private function getLotsInStockWithoutProduction(): array
    {
        $rows = DB::connection('tenant')
            ->table('boxes')
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->whereIn('pallets.status', [Pallet::STATE_REGISTERED, Pallet::STATE_STORED])
            ->whereNull('pallets.reception_id')
            ->whereNotNull('boxes.lot')
            ->where('boxes.lot', '!=', '')
            ->whereNotExists(fn ($sub) =>
                $sub->from('productions')->whereColumn('productions.lot', 'boxes.lot')
            )
            ->groupBy('boxes.lot', 'boxes.article_id')
            ->select(
                'boxes.lot',
                'boxes.article_id as product_id',
                DB::raw('COUNT(*) as boxes_count'),
                DB::raw('COALESCE(SUM(boxes.net_weight), 0) as weight_kg')
            )
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $productIds = $rows->pluck('product_id')->unique()->values()->all();
        $products   = Product::whereIn('id', $productIds)->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'lot'        => $row->lot,
            'product'    => [
                'id'   => $row->product_id,
                'name' => $products->get($row->product_id, 'Desconocido'),
            ],
            'weightKg'   => round((float) $row->weight_kg, 3),
            'boxesCount' => (int) $row->boxes_count,
        ])->all();
    }

    private function countBoxesWithoutKnownCost(): int
    {
        return Box::whereNull('manual_cost_per_kg')
            ->whereDoesntHave('productionInputs')
            ->whereHas('palletBox.pallet', fn ($q) =>
                $q->whereIn('status', [Pallet::STATE_REGISTERED, Pallet::STATE_STORED])
                  ->whereNull('reception_id')
            )
            ->count();
    }
}
