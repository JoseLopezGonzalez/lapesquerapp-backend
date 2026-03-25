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

            $boxes = $validated['boxes'] ?? [];

            // Build existing execution set for delete detection (state-sync semantics).
            $existingBoxes = collect();
            foreach ($order->pallets as $existingPallet) {
                foreach (($existingPallet->boxes ?? []) as $palletBox) {
                    if ($palletBox?->box) {
                        $existingBoxes->push($palletBox->box);
                    }
                }
            }
            $existingById = $existingBoxes->keyBy('id');

            $incomingIds = collect($boxes)
                ->map(fn ($b) => $b['id'] ?? null)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            // Create (or reuse) one pallet for newly created boxes during this sync.
            $palletForNew = $order->pallets->first();
            if (! $palletForNew) {
                $palletForNew = new Pallet;
                $palletForNew->observations = null;
                $palletForNew->status = Pallet::STATE_SHIPPED;
                $palletForNew->order_id = $order->id;
                $palletForNew->save();
            }

            // DELETE: any existing box for this order missing from payload.
            $toDelete = $existingById->keys()->diff($incomingIds)->values();
            foreach ($toDelete as $boxId) {
                $box = $existingById->get($boxId);
                if (! $box) {
                    continue;
                }
                // Safety: if consumed by production, keep invariant explicit.
                if ($box->productionInputs()->exists()) {
                    throw ValidationException::withMessages([
                        'boxes' => ['No se puede eliminar una caja ya usada en producción.'],
                    ]);
                }
                $box->palletBox?->delete(); // PalletBox::delete() deletes the Box too.
            }

            foreach ($boxes as $index => $boxData) {
                $boxId = isset($boxData['id']) ? (int) $boxData['id'] : null;
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

                if ($boxId) {
                    $existing = Box::with(['palletBox.pallet'])->find($boxId);
                    if (! $existing) {
                        throw ValidationException::withMessages([
                            'boxes' => ['La caja indicada no existe.'],
                        ]);
                    }
                    $linkedOrderId = $existing->palletBox?->pallet?->order_id;
                    if ((int) $linkedOrderId !== (int) $order->id) {
                        throw ValidationException::withMessages([
                            'boxes' => ['Una de las cajas indicadas no pertenece al pedido.'],
                        ]);
                    }

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
                    'pallet_id' => $palletForNew->id,
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

