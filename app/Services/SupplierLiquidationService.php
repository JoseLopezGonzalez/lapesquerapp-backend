<?php

namespace App\Services;

use App\Models\CeboDispatch;
use App\Models\RawMaterialReception;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class SupplierLiquidationService
{
    /**
     * Lista proveedores con actividad en recepciones o salidas de cebo en el rango de fechas.
     *
     * @param  array{start: string, end: string}  $dates
     * @return \Illuminate\Support\Collection<int, array>
     */
    public static function getSuppliersWithActivity(array $dates): Collection
    {
        $startDate = date('Y-m-d 00:00:00', strtotime($dates['start']));
        $endDate = date('Y-m-d 23:59:59', strtotime($dates['end']));

        $suppliersWithReceptions = Supplier::whereHas('rawMaterialReceptions', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })->get();

        $suppliersWithDispatches = Supplier::whereHas('ceboDispatches', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })->get();

        $allSuppliers = $suppliersWithReceptions->merge($suppliersWithDispatches)->unique('id');

        return $allSuppliers->map(function ($supplier) use ($startDate, $endDate) {
            $receptions = RawMaterialReception::where('supplier_id', $supplier->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->with('products')
                ->get();

            $dispatches = CeboDispatch::where('supplier_id', $supplier->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->with('products')
                ->get();

            $totalReceptionsWeight = $receptions->sum(fn ($r) => $r->products->sum(fn ($p) => $p->net_weight ?? 0));
            $totalDispatchesWeight = $dispatches->sum(fn ($d) => $d->products->sum(fn ($p) => $p->net_weight ?? 0));
            $totalReceptionsAmount = $receptions->sum(fn ($r) => $r->products->sum(fn ($p) => ($p->net_weight ?? 0) * ($p->price ?? 0)));
            $totalDispatchesAmount = $dispatches->sum(fn ($d) => $d->products->sum(fn ($p) => ($p->net_weight ?? 0) * ($p->price ?? 0)));

            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'receptions_count' => $receptions->count(),
                'dispatches_count' => $dispatches->count(),
                'total_receptions_weight' => round($totalReceptionsWeight, 2),
                'total_dispatches_weight' => round($totalDispatchesWeight, 2),
                'total_receptions_amount' => round($totalReceptionsAmount, 2),
                'total_dispatches_amount' => round($totalDispatchesAmount, 2),
            ];
        })->values();
    }

    /**
     * Obtiene el detalle completo de liquidación de un proveedor.
     *
     * @param  int  $supplierId
     * @param  array{start: string, end: string}  $dates
     * @return array{supplier: array, date_range: array, receptions: array, dispatches: array, summary: array}
     */
    public static function getLiquidationDetails(int $supplierId, array $dates): array
    {
        $supplier = Supplier::findOrFail($supplierId);
        $startDate = date('Y-m-d 00:00:00', strtotime($dates['start']));
        $endDate = date('Y-m-d 23:59:59', strtotime($dates['end']));

        $receptions = RawMaterialReception::where('supplier_id', $supplierId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['products.product', 'supplier'])
            ->orderBy('date', 'asc')
            ->get();

        $dispatches = CeboDispatch::where('supplier_id', $supplierId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['products.product', 'supplier'])
            ->orderBy('date', 'asc')
            ->get();

        $receptionsData = [];
        $dispatchesData = [];
        $processedDispatchIds = [];

        foreach ($receptions as $reception) {
            $relatedDispatches = $dispatches->filter(function ($dispatch) use ($reception, &$processedDispatchIds) {
                $daysDiff = abs(strtotime($dispatch->date) - strtotime($reception->date)) / (60 * 60 * 24);

                return $daysDiff <= 7 && ! in_array($dispatch->id, $processedDispatchIds);
            });

            foreach ($relatedDispatches as $dispatch) {
                $processedDispatchIds[] = $dispatch->id;
            }

            $calculatedTotalWeight = $reception->products->sum(fn ($p) => $p->net_weight ?? 0);
            $calculatedTotalAmount = $reception->products->sum(fn ($p) => ($p->net_weight ?? 0) * ($p->price ?? 0));
            $averagePrice = $calculatedTotalWeight > 0 ? $calculatedTotalAmount / $calculatedTotalWeight : 0;

            $receptionsData[] = [
                'id' => $reception->id,
                'date' => $reception->date,
                'notes' => $reception->notes,
                'products' => $reception->products->map(function ($product) {
                    $productModel = $product->product;

                    return [
                        'id' => $product->id,
                        'product' => [
                            'id' => $productModel->id ?? null,
                            'name' => $productModel->name ?? null,
                            'code' => $productModel->a3erp_code ?? $productModel->facil_com_code ?? null,
                        ],
                        'lot' => $product->lot ?? null,
                        'net_weight' => round($product->net_weight ?? 0, 2),
                        'price' => round($product->price ?? 0, 2),
                        'amount' => round(($product->net_weight ?? 0) * ($product->price ?? 0), 2),
                        'boxes' => $product->boxes ?? 0,
                    ];
                })->values()->all(),
                'declared_total_net_weight' => round($reception->declared_total_net_weight ?? 0, 2),
                'declared_total_amount' => round($reception->declared_total_amount ?? 0, 2),
                'calculated_total_net_weight' => round($calculatedTotalWeight, 2),
                'calculated_total_amount' => round($calculatedTotalAmount, 2),
                'average_price' => round($averagePrice, 2),
                'related_dispatches' => self::mapDispatchesToDetail($relatedDispatches),
            ];
        }

        foreach ($dispatches as $dispatch) {
            if (! in_array($dispatch->id, $processedDispatchIds)) {
                $dispatchRow = self::mapDispatchToDetail($dispatch);
                $dispatchesData[] = $dispatchRow;
            }
        }

        usort($receptionsData, fn ($a, $b) => strtotime($a['date']) - strtotime($b['date']));
        usort($dispatchesData, fn ($a, $b) => strtotime($a['date']) - strtotime($b['date']));

        $summary = self::calculateSummary($receptionsData, $dispatchesData);

        return [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_person' => $supplier->contact_person,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
            ],
            'date_range' => [
                'start' => $dates['start'] ?? null,
                'end' => $dates['end'] ?? null,
            ],
            'receptions' => $receptionsData,
            'dispatches' => $dispatchesData,
            'summary' => $summary,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection  $dispatches
     * @return array<int, array>
     */
    private static function mapDispatchesToDetail(Collection $dispatches): array
    {
        return $dispatches->map(fn ($d) => self::mapDispatchToDetail($d))->values()->all();
    }

    private static function mapDispatchToDetail(CeboDispatch $dispatch): array
    {
        $exportType = $dispatch->export_type ?? 'facilcom';
        $ivaRate = ($exportType === 'a3erp') ? 0.10 : 0.00;
        $baseAmount = round($dispatch->products->sum(fn ($p) => ($p->net_weight ?? 0) * ($p->price ?? 0)), 2);
        $ivaAmount = round($baseAmount * $ivaRate, 2);
        $totalAmount = round($baseAmount + $ivaAmount, 2);

        return [
            'id' => $dispatch->id,
            'date' => $dispatch->date,
            'notes' => $dispatch->notes,
            'export_type' => $exportType,
            'iva_rate' => $ivaRate * 100,
            'products' => $dispatch->products->map(function ($product) {
                $productModel = $product->product;

                return [
                    'id' => $product->id,
                    'product' => [
                        'id' => $productModel->id ?? null,
                        'name' => $productModel->name ?? null,
                        'code' => $productModel->a3erp_code ?? $productModel->facil_com_code ?? null,
                    ],
                    'net_weight' => round($product->net_weight ?? 0, 2),
                    'price' => round($product->price ?? 0, 2),
                    'amount' => round(($product->net_weight ?? 0) * ($product->price ?? 0), 2),
                ];
            })->values()->all(),
            'total_net_weight' => round($dispatch->products->sum(fn ($p) => $p->net_weight ?? 0), 2),
            'base_amount' => $baseAmount,
            'iva_amount' => $ivaAmount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Filtra recepciones y despachos según los IDs seleccionados para el PDF.
     *
     * @param  array  $details
     * @param  array<int>  $selectedReceptionIds
     * @param  array<int>  $selectedDispatchIds
     * @return array{0: array, 1: array}
     */
    public static function filterDetailsForPdf(array $details, array $selectedReceptionIds, array $selectedDispatchIds): array
    {
        $filteredReceptions = $details['receptions'] ?? [];
        $filteredDispatches = $details['dispatches'] ?? [];

        if (! empty($selectedReceptionIds)) {
            $filteredReceptions = array_values(array_filter($details['receptions'] ?? [], fn ($r) => in_array((int) $r['id'], $selectedReceptionIds, true)));
        }

        if (! empty($selectedDispatchIds)) {
            $filteredDispatches = array_values(array_filter($details['dispatches'] ?? [], fn ($d) => in_array((int) $d['id'], $selectedDispatchIds, true)));

            foreach ($filteredReceptions as &$reception) {
                if (! empty($reception['related_dispatches'])) {
                    $reception['related_dispatches'] = array_values(array_filter(
                        $reception['related_dispatches'],
                        fn ($d) => in_array((int) $d['id'], $selectedDispatchIds, true)
                    ));
                }
            }
            unset($reception);

            $existingDispatchIds = array_map(fn ($d) => (int) $d['id'], $filteredDispatches);
            foreach ($details['receptions'] ?? [] as $reception) {
                if (! in_array((int) $reception['id'], $selectedReceptionIds, true) && ! empty($reception['related_dispatches'])) {
                    foreach ($reception['related_dispatches'] as $dispatch) {
                        $dispatchId = (int) $dispatch['id'];
                        if (in_array($dispatchId, $selectedDispatchIds, true) && ! in_array($dispatchId, $existingDispatchIds, true)) {
                            $filteredDispatches[] = $dispatch;
                            $existingDispatchIds[] = $dispatchId;
                        }
                    }
                }
            }
        }

        return [$filteredReceptions, $filteredDispatches];
    }

    /**
     * @param  array  $receptions
     * @param  array  $dispatches
     * @return array<string, mixed>
     */
    public static function calculateSummary(array $receptions, array $dispatches): array
    {
        $allDispatchIds = [];
        foreach ($dispatches as $d) {
            $allDispatchIds[] = $d['id'];
        }
        foreach ($receptions as $r) {
            foreach ($r['related_dispatches'] ?? [] as $d) {
                if (! in_array($d['id'], $allDispatchIds)) {
                    $allDispatchIds[] = $d['id'];
                }
            }
        }
        $totalDispatches = count($allDispatchIds);

        $totalCalculatedWeight = array_sum(array_column($receptions, 'calculated_total_net_weight'));
        $totalCalculatedAmount = array_sum(array_column($receptions, 'calculated_total_amount'));
        $independentDispatchIds = array_map(fn ($d) => (int) ($d['id'] ?? 0), $dispatches);

        $totalDispatchesWeight = array_sum(array_column($dispatches, 'total_net_weight'));
        $totalDispatchesBaseAmount = array_sum(array_column($dispatches, 'base_amount'));
        $totalDispatchesIvaAmount = array_sum(array_column($dispatches, 'iva_amount'));
        $totalDispatchesAmount = array_sum(array_column($dispatches, 'total_amount'));

        foreach ($receptions as $r) {
            foreach ($r['related_dispatches'] ?? [] as $d) {
                $dispatchId = (int) ($d['id'] ?? 0);
                if (! in_array($dispatchId, $independentDispatchIds, true)) {
                    $totalDispatchesWeight += $d['total_net_weight'] ?? 0;
                    $totalDispatchesBaseAmount += $d['base_amount'] ?? 0;
                    $totalDispatchesIvaAmount += $d['iva_amount'] ?? 0;
                    $totalDispatchesAmount += $d['total_amount'] ?? 0;
                }
            }
        }

        $totalDeclaredWeight = array_sum(array_column($receptions, 'declared_total_net_weight'));
        $totalDeclaredAmount = array_sum(array_column($receptions, 'declared_total_amount'));
        $weightDifference = $totalCalculatedWeight - $totalDeclaredWeight;
        $amountDifference = $totalCalculatedAmount - $totalDeclaredAmount;
        $percentageNotDeclared = $totalCalculatedAmount > 0
            ? round(($amountDifference / $totalCalculatedAmount) * 100, 2)
            : 0;
        $hasIvaInDispatches = $totalDispatchesIvaAmount > 0;
        $totalDeclaredWithIva = round($totalDeclaredAmount * 1.10, 2);

        return [
            'total_receptions' => count($receptions),
            'total_dispatches' => $totalDispatches,
            'total_receptions_weight' => round($totalCalculatedWeight, 2),
            'total_receptions_amount' => round($totalCalculatedAmount, 2),
            'total_dispatches_weight' => round($totalDispatchesWeight, 2),
            'total_dispatches_base_amount' => round($totalDispatchesBaseAmount, 2),
            'total_dispatches_iva_amount' => round($totalDispatchesIvaAmount, 2),
            'total_dispatches_amount' => round($totalDispatchesAmount, 2),
            'total_declared_weight' => round($totalDeclaredWeight, 2),
            'total_declared_amount' => round($totalDeclaredAmount, 2),
            'total_declared_with_iva' => $totalDeclaredWithIva,
            'weight_difference' => round($weightDifference, 2),
            'amount_difference' => round($amountDifference, 2),
            'percentage_not_declared' => $percentageNotDeclared,
            'net_amount' => round($amountDifference, 2),
            'has_iva_in_dispatches' => $hasIvaInDispatches,
        ];
    }

    /**
     * @param  array  $summary
     * @param  string|null  $paymentMethod
     * @param  bool  $hasManagementFee
     * @return array<string, mixed>
     */
    public static function calculatePaymentTotals(array $summary, ?string $paymentMethod, bool $hasManagementFee): array
    {
        $hasIvaInDispatches = $summary['has_iva_in_dispatches'] ?? false;
        $totalReception = $summary['total_receptions_amount'] ?? 0;
        $totalDeclared = $summary['total_declared_amount'] ?? 0;
        $totalDeclaredWithIva = $summary['total_declared_with_iva'] ?? 0;
        $totalDispatchesAmount = $summary['total_dispatches_amount'] ?? 0;
        $totalDispatchesBaseAmount = $summary['total_dispatches_base_amount'] ?? 0;

        $result = [
            'has_iva_in_dispatches' => $hasIvaInDispatches,
            'payment_method' => $paymentMethod,
            'has_management_fee' => $hasManagementFee,
            'total_cash' => null,
            'total_transfer' => null,
            'management_fee' => null,
            'total_transfer_final' => null,
        ];

        if ($hasIvaInDispatches) {
            if ($paymentMethod === 'cash') {
                $result['total_cash'] = round($totalReception - $totalDeclared - $totalDispatchesAmount, 2);
                $result['total_transfer'] = round($totalDeclaredWithIva, 2);
            } elseif ($paymentMethod === 'transfer') {
                $result['total_cash'] = round($totalReception - $totalDeclared, 2);
                $result['total_transfer'] = round($totalDeclaredWithIva - $totalDispatchesAmount, 2);
            } else {
                $result['total_cash'] = round($totalReception - $totalDeclared, 2);
                $result['total_transfer'] = round($totalDeclaredWithIva, 2);
            }
        } elseif ($totalDispatchesBaseAmount > 0) {
            $result['total_cash'] = round($totalReception - $totalDeclared - $totalDispatchesBaseAmount, 2);
            $result['total_transfer'] = round($totalDeclaredWithIva, 2);
        } else {
            $result['total_cash'] = round($totalReception - $totalDeclared, 2);
            $result['total_transfer'] = round($totalDeclaredWithIva, 2);
        }

        if ($hasManagementFee) {
            $result['management_fee'] = round($totalDeclared * 0.025, 2);
            $result['total_transfer_final'] = round($result['total_transfer'] - $result['management_fee'], 2);
        } else {
            $result['total_transfer_final'] = $result['total_transfer'];
        }

        return $result;
    }
}
