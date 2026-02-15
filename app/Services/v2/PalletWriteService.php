<?php

namespace App\Services\v2;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\RawMaterialReception;
use App\Models\StoredPallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PalletWriteService
{
    /**
     * Crea un palet con sus cajas.
     */
    public static function store(array $validated): Pallet
    {
        $boxes = $validated['boxes'];
        $storeId = $validated['store']['id'] ?? null;

        $newPallet = new Pallet;
        $newPallet->observations = $validated['observations'];
        $newPallet->status = $storeId
            ? Pallet::STATE_STORED
            : ($validated['state']['id'] ?? Pallet::STATE_REGISTERED);
        $newPallet->order_id = $validated['orderId'] ?? null;
        $newPallet->save();

        if ($storeId) {
            StoredPallet::create([
                'pallet_id' => $newPallet->id,
                'store_id' => $storeId,
            ]);
        }

        foreach ($boxes as $box) {
            $newBox = Box::create([
                'article_id' => $box['product']['id'],
                'lot' => $box['lot'],
                'gs1_128' => $box['gs1128'],
                'gross_weight' => $box['grossWeight'],
                'net_weight' => $box['netWeight'],
            ]);
            PalletBox::create([
                'pallet_id' => $newPallet->id,
                'box_id' => $newBox->id,
            ]);
        }

        $newPallet->refresh();

        return PalletListService::loadRelations(Pallet::query()->where('id', $newPallet->id))->firstOrFail();
    }

    /**
     * Valida si un palet de recepción puede ser editado. Retorna mensaje de error o null.
     */
    public static function validateUpdatePermissions(Pallet $pallet): ?string
    {
        if ($pallet->reception_id === null) {
            return null;
        }
        $reception = $pallet->reception;
        if ($reception->creation_mode !== RawMaterialReception::CREATION_MODE_PALLETS) {
            return 'No se puede modificar un palet que proviene de una recepción creada por líneas. Modifique desde la recepción.';
        }
        if ($pallet->order_id !== null) {
            return 'No se puede modificar el palet: está vinculado a un pedido';
        }
        foreach ($pallet->boxes as $palletBox) {
            if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                return "No se puede modificar el palet: la caja #{$palletBox->box->id} está siendo usada en producción";
            }
        }

        return null;
    }

    /**
     * Actualiza un palet.
     */
    public static function update(Request $request, Pallet $pallet, array $validated): Pallet
    {
        return DB::transaction(function () use ($request, $pallet, $validated) {
            $palletData = $validated;
            $updatedPallet = $pallet;

            $wasUnlinked = false;
            if ($request->has('orderId')) {
                if ($palletData['orderId'] == null && $updatedPallet->order_id !== null) {
                    $updatedPallet->order_id = null;
                    $wasUnlinked = true;
                } else {
                    $updatedPallet->order_id = $palletData['orderId'];
                }
            }

            $stateWasManuallyChanged = false;
            if ($request->has('state')) {
                if ($updatedPallet->status != $palletData['state']['id']) {
                    if ($updatedPallet->store != null && $palletData['state']['id'] != Pallet::STATE_STORED) {
                        $updatedPallet->unStore();
                    }
                    $updatedPallet->status = $palletData['state']['id'];
                    $stateWasManuallyChanged = true;
                }
            }

            if ($wasUnlinked && ! $stateWasManuallyChanged) {
                if ($updatedPallet->status !== Pallet::STATE_REGISTERED) {
                    $updatedPallet->status = Pallet::STATE_REGISTERED;
                }
                $updatedPallet->unStore();
            }

            if ($request->has('observations')) {
                if ($palletData['observations'] != $updatedPallet->observations) {
                    $updatedPallet->observations = $palletData['observations'];
                }
            }

            $updatedPallet->save();

            if (array_key_exists('store', $palletData)) {
                $storeId = $palletData['store']['id'] ?? null;
                $isPalletStored = StoredPallet::where('pallet_id', $updatedPallet->id)->first();
                if ($isPalletStored) {
                    if ($isPalletStored->store_id != $storeId) {
                        $isPalletStored->delete();
                        if ($storeId) {
                            StoredPallet::create([
                                'pallet_id' => $updatedPallet->id,
                                'store_id' => $storeId,
                            ]);
                        }
                    }
                } elseif ($storeId) {
                    StoredPallet::create([
                        'pallet_id' => $updatedPallet->id,
                        'store_id' => $storeId,
                    ]);
                }
            }

            if (array_key_exists('boxes', $palletData)) {
                $boxes = $palletData['boxes'];

                $updatedPallet->boxes->map(function ($palletBox) use (&$boxes) {
                    $hasBeenUpdated = false;
                    foreach ($boxes as $index => $updatedBox) {
                        if ($updatedBox['id'] == $palletBox->box->id) {
                            $palletBox->box->update([
                                'article_id' => $updatedBox['product']['id'],
                                'lot' => $updatedBox['lot'],
                                'gs1_128' => $updatedBox['gs1128'],
                                'gross_weight' => $updatedBox['grossWeight'],
                                'net_weight' => $updatedBox['netWeight'],
                            ]);
                            $hasBeenUpdated = true;
                            unset($boxes[$index]);
                        }
                    }
                    if (! $hasBeenUpdated) {
                        $palletBox->box->delete();
                    }
                });

                $boxes = array_values($boxes);
                foreach ($boxes as $box) {
                    $newBox = Box::create([
                        'article_id' => $box['product']['id'],
                        'lot' => $box['lot'],
                        'gs1_128' => $box['gs1128'],
                        'gross_weight' => $box['grossWeight'],
                        'net_weight' => $box['netWeight'],
                    ]);
                    PalletBox::create([
                        'pallet_id' => $updatedPallet->id,
                        'box_id' => $newBox->id,
                    ]);
                }
            }

            $updatedPallet->refresh();

            if ($updatedPallet->reception_id !== null) {
                $reception = $updatedPallet->reception;
                if ($reception && $reception->creation_mode === RawMaterialReception::CREATION_MODE_PALLETS) {
                    self::updateReceptionLinesFromPallets($reception);
                }
            }

            return PalletListService::loadRelations(Pallet::query()->where('id', $updatedPallet->id))->firstOrFail();
        });
    }

    /**
     * Actualizar líneas de recepción basándose en los palets actualizados.
     */
    public static function updateReceptionLinesFromPallets(RawMaterialReception $reception): void
    {
        $reception->load('pallets.boxes.box', 'products');

        $existingPrices = [];
        foreach ($reception->products as $product) {
            $productId = $product->product_id;
            $lot = $product->lot;
            $key = "{$productId}_{$lot}";
            if (! isset($existingPrices[$key]) ||
                ($product->net_weight > ($existingPrices[$key]['weight'] ?? 0))) {
                $existingPrices[$key] = [
                    'price' => $product->price,
                    'weight' => $product->net_weight,
                ];
            }
        }

        $groupedByProduct = [];

        foreach ($reception->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (! $box) {
                    continue;
                }
                $productId = $box->article_id;
                $lot = $box->lot;
                $netWeight = $box->net_weight ?? 0;
                $key = "{$productId}_{$lot}";
                $price = $existingPrices[$key]['price'] ?? null;
                if (! isset($groupedByProduct[$key])) {
                    $groupedByProduct[$key] = [
                        'product_id' => $productId,
                        'lot' => $lot,
                        'net_weight' => 0,
                        'price' => $price,
                    ];
                }
                $groupedByProduct[$key]['net_weight'] += $netWeight;
            }
        }

        $reception->products()->delete();

        foreach ($groupedByProduct as $group) {
            if ($group['net_weight'] > 0) {
                $reception->products()->create([
                    'product_id' => $group['product_id'],
                    'lot' => $group['lot'],
                    'net_weight' => $group['net_weight'],
                    'price' => $group['price'],
                ]);
            }
        }
    }

    /**
     * Elimina un palet (validar reception_id antes de llamar).
     */
    public static function destroy(Pallet $pallet): void
    {
        DB::transaction(function () use ($pallet) {
            if ($pallet->storedPallet) {
                $pallet->storedPallet->delete();
            }
            $pallet->boxes()->delete();
            $pallet->delete();
        });
    }

    /**
     * Elimina múltiples palets (validar que ninguno tenga reception_id antes de llamar).
     */
    public static function destroyMultiple(array $palletIds): void
    {
        DB::transaction(function () use ($palletIds) {
            StoredPallet::whereIn('pallet_id', $palletIds)->delete();
            PalletBox::whereIn('pallet_id', $palletIds)->delete();
            Pallet::whereIn('id', $palletIds)->delete();
        });
    }
}
