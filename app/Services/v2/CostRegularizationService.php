<?php

namespace App\Services\v2;

use App\Models\Box;
use App\Models\Order;
use App\Models\Pallet;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CostRegularizationService
{
    public function salesMissingCostBoxes(array $filters): array
    {
        $rows = $this->salesMissingCostRows($filters);

        return $this->buildSalesResponse($rows, $filters);
    }

    public function stockMissingCostBoxes(array $filters): array
    {
        $rows = $this->stockMissingCostRows($filters);

        return $this->buildStockResponse($rows);
    }

    public function applyManualCostsByProduct(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $scope = $payload['scope'];
            $filters = $payload['filters'];
            $costsByProduct = collect($payload['productCosts'])
                ->mapWithKeys(fn (array $row): array => [(int) $row['productId'] => (float) $row['manualCostPerKg']]);

            $rows = $scope === 'sales'
                ? $this->salesMissingCostRows($filters)
                : $this->stockMissingCostRows($filters);

            $targetRows = $rows->filter(fn (array $row): bool => $costsByProduct->has((int) $row['product']['id']));
            $products = [];
            $updatedBoxesCount = 0;
            $updatedNetWeightKg = 0.0;
            $estimatedManualCost = 0.0;

            foreach ($targetRows as $row) {
                /** @var Box|null $box */
                $box = Box::query()->lockForUpdate()->find($row['id']);
                if (! $box || ! $this->boxIsMissingCost($box)) {
                    continue;
                }

                $productId = (int) $box->article_id;
                $manualCostPerKg = (float) $costsByProduct->get($productId);
                $weight = (float) $box->net_weight;

                $box->manual_cost_per_kg = $manualCostPerKg;
                $box->save();

                if (! isset($products[$productId])) {
                    $products[$productId] = [
                        'product' => $row['product'],
                        'manualCostPerKg' => round($manualCostPerKg, 4),
                        'updatedBoxesCount' => 0,
                        'updatedNetWeightKg' => 0.0,
                        'estimatedManualCost' => 0.0,
                    ];
                }

                $products[$productId]['updatedBoxesCount']++;
                $products[$productId]['updatedNetWeightKg'] += $weight;
                $products[$productId]['estimatedManualCost'] += $weight * $manualCostPerKg;

                $updatedBoxesCount++;
                $updatedNetWeightKg += $weight;
                $estimatedManualCost += $weight * $manualCostPerKg;
            }

            $products = array_map(function (array $row): array {
                $row['updatedNetWeightKg'] = round($row['updatedNetWeightKg'], 3);
                $row['estimatedManualCost'] = round($row['estimatedManualCost'], 2);

                return $row;
            }, array_values($products));

            return [
                'scope' => $scope,
                'updatedBoxesCount' => $updatedBoxesCount,
                'skippedBoxesCount' => $targetRows->count() - $updatedBoxesCount,
                'updatedNetWeightKg' => round($updatedNetWeightKg, 3),
                'estimatedManualCost' => round($estimatedManualCost, 2),
                'products' => $products,
            ];
        });
    }

    public function applyManualCostsByLotProduct(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $scope = $payload['scope'];
            $filters = $payload['filters'];
            $costsByLotProduct = collect($payload['lotProductCosts'])
                ->mapWithKeys(fn (array $row): array => [
                    $this->lotProductKey((int) $row['productId'], $row['lot']) => (float) $row['manualCostPerKg'],
                ]);

            $rows = $scope === 'sales'
                ? $this->salesMissingCostRows($filters)
                : $this->stockMissingCostRows($filters);

            $targetRows = $rows->filter(fn (array $row): bool => $costsByLotProduct->has(
                $this->lotProductKey((int) $row['product']['id'], $row['lot'])
            ));
            $lotProducts = [];
            $updatedBoxesCount = 0;
            $updatedNetWeightKg = 0.0;
            $estimatedManualCost = 0.0;

            foreach ($targetRows as $row) {
                /** @var Box|null $box */
                $box = Box::query()->lockForUpdate()->find($row['id']);
                if (! $box || ! $this->boxIsMissingCost($box)) {
                    continue;
                }

                $productId = (int) $box->article_id;
                $lot = $box->lot;
                $key = $this->lotProductKey($productId, $lot);
                $manualCostPerKg = (float) $costsByLotProduct->get($key);
                $weight = (float) $box->net_weight;

                $box->manual_cost_per_kg = $manualCostPerKg;
                $box->save();

                if (! isset($lotProducts[$key])) {
                    $lotProducts[$key] = [
                        'product' => $row['product'],
                        'lot' => $lot,
                        'manualCostPerKg' => round($manualCostPerKg, 4),
                        'updatedBoxesCount' => 0,
                        'updatedNetWeightKg' => 0.0,
                        'estimatedManualCost' => 0.0,
                    ];
                }

                $lotProducts[$key]['updatedBoxesCount']++;
                $lotProducts[$key]['updatedNetWeightKg'] += $weight;
                $lotProducts[$key]['estimatedManualCost'] += $weight * $manualCostPerKg;

                $updatedBoxesCount++;
                $updatedNetWeightKg += $weight;
                $estimatedManualCost += $weight * $manualCostPerKg;
            }

            $lotProducts = array_map(function (array $row): array {
                $row['updatedNetWeightKg'] = round($row['updatedNetWeightKg'], 3);
                $row['estimatedManualCost'] = round($row['estimatedManualCost'], 2);

                return $row;
            }, array_values($lotProducts));

            return [
                'scope' => $scope,
                'updatedBoxesCount' => $updatedBoxesCount,
                'skippedBoxesCount' => $targetRows->count() - $updatedBoxesCount,
                'updatedNetWeightKg' => round($updatedNetWeightKg, 3),
                'estimatedManualCost' => round($estimatedManualCost, 2),
                'lotProducts' => $lotProducts,
            ];
        });
    }

    private function salesMissingCostRows(array $filters): Collection
    {
        return $this->salesCandidateBoxes($filters)
            ->filter(fn (array $row): bool => $this->boxIsMissingCost($row['box']))
            ->map(fn (array $row): array => $this->formatSalesBoxRow($row))
            ->values();
    }

    private function stockMissingCostRows(array $filters): Collection
    {
        return $this->stockCandidateBoxes($filters)
            ->filter(fn (Box $box): bool => $this->boxIsMissingCost($box))
            ->map(fn (Box $box): array => $this->formatStockBoxRow($box))
            ->values();
    }

    private function salesCandidateBoxes(array $filters): Collection
    {
        $orders = Order::query()
            ->where('status', Order::STATUS_FINISHED)
            ->whereBetween('load_date', [
                $filters['dateFrom'].' 00:00:00',
                $filters['dateTo'].' 23:59:59',
            ])
            ->when(! empty($filters['customerIds']), fn (Builder $query) => $query->whereIn('customer_id', $filters['customerIds']))
            ->when(! empty($filters['orderIds']), fn (Builder $query) => $query->whereIn('id', $filters['orderIds']))
            ->with([
                'customer',
                'pallets.reception.products',
                'pallets.boxes.box.product',
                'pallets.boxes.box.palletBox.pallet.reception.products',
            ])
            ->get();

        $rows = collect();
        foreach ($orders as $order) {
            foreach ($order->pallets as $pallet) {
                foreach ($pallet->boxes as $palletBox) {
                    $box = $palletBox->box;
                    if (! $box) {
                        continue;
                    }

                    if (! empty($filters['productIds']) && ! in_array((int) $box->article_id, $filters['productIds'], true)) {
                        continue;
                    }

                    $rows->push([
                        'box' => $box,
                        'pallet' => $pallet,
                        'order' => $order,
                    ]);
                }
            }
        }

        return $rows;
    }

    private function stockCandidateBoxes(array $filters): Collection
    {
        $pallets = Pallet::query()
            ->whereIn('status', [Pallet::STATE_REGISTERED, Pallet::STATE_STORED])
            ->where(function (Builder $query): void {
                $query->whereNull('order_id')
                    ->orWhereHas('order', fn (Builder $orderQuery) => $orderQuery->whereNotIn('status', [
                        Order::STATUS_FINISHED,
                        Order::STATUS_INCIDENT,
                    ]));
            })
            ->when(! empty($filters['storeIds']), function (Builder $query) use ($filters): void {
                $query->whereHas('storedPallet', fn (Builder $storedQuery) => $storedQuery->whereIn('store_id', $filters['storeIds']));
            })
            ->with([
                'order',
                'storedPallet.store',
                'reception.products',
                'boxes.box.product',
                'boxes.box.palletBox.pallet.reception.products',
                'boxes.box.palletBox.pallet.storedPallet.store',
            ])
            ->get();

        $boxes = collect();
        foreach ($pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (! $box) {
                    continue;
                }

                if (! empty($filters['productIds']) && ! in_array((int) $box->article_id, $filters['productIds'], true)) {
                    continue;
                }

                if (($filters['lot'] ?? null) !== null && $filters['lot'] !== '' && $box->lot !== $filters['lot']) {
                    continue;
                }

                if (($filters['createdFrom'] ?? null) !== null && $box->created_at?->lt(Carbon::parse($filters['createdFrom'])->startOfDay())) {
                    continue;
                }

                if (($filters['createdTo'] ?? null) !== null && $box->created_at?->gt(Carbon::parse($filters['createdTo'])->endOfDay())) {
                    continue;
                }

                $boxes->push($box);
            }
        }

        return $boxes;
    }

    private function boxIsMissingCost(Box $box): bool
    {
        return $box->manual_cost_per_kg === null && $box->traceable_cost_per_kg === null;
    }

    private function formatSalesBoxRow(array $row): array
    {
        /** @var Box $box */
        $box = $row['box'];
        $pallet = $row['pallet'];
        $order = $row['order'];

        return [
            'id' => $box->id,
            'palletId' => $pallet->id,
            'orderId' => $order->id,
            'orderFormattedId' => $order->formatted_id,
            'loadDate' => $order->load_date ? Carbon::parse($order->load_date)->format('Y-m-d') : null,
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
            ] : null,
            'product' => $this->productPayload($box->product, (int) $box->article_id),
            'lot' => $box->lot,
            'gs1128' => $box->gs1_128,
            'netWeightKg' => round((float) $box->net_weight, 3),
            'traceableCostPerKg' => null,
            'manualCostPerKg' => null,
        ];
    }

    private function formatStockBoxRow(Box $box): array
    {
        $pallet = $box->pallet;

        return [
            'id' => $box->id,
            'palletId' => $pallet?->id,
            'palletState' => $pallet ? Pallet::getStateName((int) $pallet->status) : null,
            'store' => $pallet?->storedPallet?->store ? [
                'id' => $pallet->storedPallet->store->id,
                'name' => $pallet->storedPallet->store->name,
            ] : null,
            'product' => $this->productPayload($box->product, (int) $box->article_id),
            'lot' => $box->lot,
            'gs1128' => $box->gs1_128,
            'netWeightKg' => round((float) $box->net_weight, 3),
            'traceableCostPerKg' => null,
            'manualCostPerKg' => null,
        ];
    }

    private function buildSalesResponse(Collection $rows, array $filters): array
    {
        return [
            'period' => [
                'from' => $filters['dateFrom'],
                'to' => $filters['dateTo'],
            ],
            'summary' => $this->summaryPayload($rows, 'ordersCount'),
            'products' => $this->productsPayload($rows, 'ordersCount'),
            'lotProducts' => $this->lotProductsPayload($rows, 'ordersCount'),
            'boxes' => $rows->values()->all(),
        ];
    }

    private function buildStockResponse(Collection $rows): array
    {
        return [
            'summary' => $this->summaryPayload($rows, 'palletsCount'),
            'products' => $this->productsPayload($rows, 'palletsCount'),
            'lotProducts' => $this->lotProductsPayload($rows, 'palletsCount'),
            'boxes' => $rows->values()->all(),
        ];
    }

    private function summaryPayload(Collection $rows, string $countKey): array
    {
        $countIds = $countKey === 'ordersCount'
            ? $rows->pluck('orderId')->filter()->unique()->count()
            : $rows->pluck('palletId')->filter()->unique()->count();

        return [
            'boxesCount' => $rows->count(),
            'netWeightKg' => round((float) $rows->sum('netWeightKg'), 3),
            'productsCount' => $rows->pluck('product.id')->filter()->unique()->count(),
            $countKey => $countIds,
        ];
    }

    private function productsPayload(Collection $rows, string $countKey): array
    {
        return $rows
            ->groupBy(fn (array $row): int => (int) $row['product']['id'])
            ->map(function (Collection $productRows) use ($countKey): array {
                $first = $productRows->first();
                $countIds = $countKey === 'ordersCount'
                    ? $productRows->pluck('orderId')->filter()->unique()->count()
                    : $productRows->pluck('palletId')->filter()->unique()->count();

                return [
                    'product' => $first['product'],
                    'boxesCount' => $productRows->count(),
                    'netWeightKg' => round((float) $productRows->sum('netWeightKg'), 3),
                    $countKey => $countIds,
                    'suggestedManualCostPerKg' => null,
                ];
            })
            ->values()
            ->all();
    }

    private function lotProductsPayload(Collection $rows, string $countKey): array
    {
        return $rows
            ->groupBy(fn (array $row): string => $this->lotProductKey((int) $row['product']['id'], $row['lot']))
            ->map(function (Collection $lotRows) use ($countKey): array {
                $first = $lotRows->first();
                $countIds = $countKey === 'ordersCount'
                    ? $lotRows->pluck('orderId')->filter()->unique()->count()
                    : $lotRows->pluck('palletId')->filter()->unique()->count();

                return [
                    'product' => $first['product'],
                    'lot' => $first['lot'],
                    'boxesCount' => $lotRows->count(),
                    'netWeightKg' => round((float) $lotRows->sum('netWeightKg'), 3),
                    $countKey => $countIds,
                    'suggestedManualCostPerKg' => null,
                ];
            })
            ->values()
            ->all();
    }

    private function productPayload(?Product $product, int $fallbackId): array
    {
        return [
            'id' => $product?->id ?? $fallbackId,
            'name' => $product?->name,
        ];
    }

    private function lotProductKey(int $productId, mixed $lot): string
    {
        return $productId.'|'.($lot ?? '');
    }
}
