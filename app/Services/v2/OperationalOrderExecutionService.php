<?php

namespace App\Services\v2;

use App\Models\Box;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\Pallet;
use App\Models\PalletBox;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalOrderExecutionService
{
    public static function execute(Order $order, array $validated): Order
    {
        if (! $order->canBeEditedOperationally()) {
            throw ValidationException::withMessages([
                'order' => ['El pedido no se puede editar operativamente en su estado actual.'],
            ]);
        }

        return DB::transaction(function () use ($order, $validated) {
            $order->load(['plannedProductDetails', 'pallets.boxes.box']);

            $pallet = Pallet::create([
                'observations' => null,
                'status' => Pallet::STATE_SHIPPED,
                'order_id' => $order->id,
            ]);

            $boxes = $validated['boxes'] ?? [];
            foreach ($boxes as $index => $boxData) {
                $productId = (int) $boxData['productId'];
                $netWeight = (float) $boxData['netWeight'];

                $lot = trim((string) ($boxData['lot'] ?? ''));
                if ($lot === '') {
                    $lot = 'FIELD-ORDER-' . $order->id . '-' . ($index + 1);
                }

                $gs1128 = trim((string) ($boxData['gs1128'] ?? ''));
                if ($gs1128 === '') {
                    // DB column is non-null in migrations; keep deterministic-ish value for idempotence.
                    $gs1128 = 'FIELD-ORDER-' . $order->id . '-' . $productId . '-' . $lot;
                    $gs1128 = mb_substr($gs1128, 0, 255);
                }

                $existing = null;
                if ($gs1128 !== '') {
                    $candidates = Box::query()
                        ->where('gs1_128', $gs1128)
                        ->with(['palletBox.pallet'])
                        ->get();

                    $existing = $candidates->first(function (Box $candidate) use ($order) {
                        $linkedOrderId = $candidate->palletBox?->pallet?->order_id;

                        return $linkedOrderId === null || (int) $linkedOrderId === (int) $order->id;
                    }) ?? $candidates->first();
                } else {
                    $existing = Box::query()
                        ->where('article_id', $productId)
                        ->where('lot', $lot)
                        ->with(['palletBox.pallet'])
                        ->first();
                }

                if ($existing) {
                    $linkedOrderIds = PalletBox::query()
                        ->where('box_id', $existing->id)
                        ->join('pallets', 'pallet_boxes.pallet_id', '=', 'pallets.id')
                        ->pluck('pallets.order_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();

                    $hasOtherOrderLink = ! empty($linkedOrderIds) && ! in_array((int) $order->id, $linkedOrderIds, true);
                    if ($hasOtherOrderLink) {
                        throw ValidationException::withMessages([
                            'boxes' => ['Una de las cajas escaneadas ya está vinculada a otro pedido.'],
                        ]);
                    }

                    if (! $existing->palletBox) {
                        PalletBox::create([
                            'pallet_id' => $pallet->id,
                            'box_id' => $existing->id,
                        ]);
                    }

                    // Keep weights up to date if resent.
                    $existing->gross_weight = $boxData['grossWeight'] ?? $existing->gross_weight ?? $netWeight;
                    $existing->net_weight = $netWeight;
                    $existing->lot = $lot;
                    $existing->gs1_128 = $gs1128;
                    $existing->article_id = $productId;
                    $existing->save();

                    continue;
                }

                $newBox = Box::create([
                    'article_id' => $productId,
                    'lot' => $lot,
                    'gs1_128' => $gs1128,
                    'gross_weight' => $boxData['grossWeight'] ?? $netWeight,
                    'net_weight' => $netWeight,
                ]);

                PalletBox::create([
                    'pallet_id' => $pallet->id,
                    'box_id' => $newBox->id,
                ]);
            }

            $boxesByProduct = [];
            foreach ($boxes as $b) {
                $pid = (int) $b['productId'];
                if (! isset($boxesByProduct[$pid])) {
                    $boxesByProduct[$pid] = ['boxes' => 0, 'netWeight' => 0.0];
                }
                $boxesByProduct[$pid]['boxes']++;
                $boxesByProduct[$pid]['netWeight'] += (float) $b['netWeight'];
            }

            foreach (($validated['plannedExtras'] ?? []) as $extra) {
                $productId = (int) $extra['productId'];
                $alreadyPlanned = $order->plannedProductDetails->firstWhere('product_id', $productId);
                if ($alreadyPlanned) {
                    throw ValidationException::withMessages([
                        'plannedExtras' => ['Uno de los productos extra ya está contemplado en las líneas planificadas del pedido.'],
                    ]);
                }

                $derived = $boxesByProduct[$productId] ?? ['boxes' => 0, 'netWeight' => 0.0];

                OrderPlannedProductDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'tax_id' => (int) $extra['taxId'],
                    'quantity' => (float) $derived['netWeight'],
                    'boxes' => (int) $derived['boxes'],
                    'unit_price' => (float) $extra['unitPrice'],
                ]);
            }

            foreach (($validated['plannedAdjustments'] ?? []) as $adj) {
                $detail = OrderPlannedProductDetail::find((int) $adj['plannedProductDetailId']);
                if (! $detail || (int) $detail->order_id !== (int) $order->id) {
                    throw ValidationException::withMessages([
                        'plannedAdjustments' => ['Una de las líneas a ajustar no pertenece al pedido.'],
                    ]);
                }

                $detail->unit_price = (float) $adj['unitPrice'];
                $detail->tax_id = (int) $adj['taxId'];
                $detail->save();
            }

            return OrderDetailService::getOrderForDetail((string) $order->id);
        });
    }
}

