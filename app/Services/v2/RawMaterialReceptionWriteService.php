<?php

namespace App\Services\v2;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\Product;
use App\Models\RawMaterialReception;
use App\Models\StoredPallet;
use Illuminate\Database\Eloquent\Model;

class RawMaterialReceptionWriteService
{
    /**
     * Crear una recepción de materia prima (modo líneas o palets).
     * Debe ejecutarse dentro de una transacción.
     *
     * @param  array  $data  Array validado (supplier.id, date, notes, declaredTotalAmount, declaredTotalNetWeight, pallets|details, prices si pallets)
     */
    public static function store(array $data): RawMaterialReception
    {
        $reception = new RawMaterialReception();
        $reception->supplier_id = $data['supplier']['id'];
        $reception->date = $data['date'];
        $reception->notes = $data['notes'] ?? null;
        $reception->declared_total_amount = $data['declaredTotalAmount'] ?? null;
        $reception->declared_total_net_weight = $data['declaredTotalNetWeight'] ?? null;

        if (! empty($data['pallets'] ?? [])) {
            $reception->creation_mode = RawMaterialReception::CREATION_MODE_PALLETS;
        } else {
            $reception->creation_mode = RawMaterialReception::CREATION_MODE_LINES;
        }

        $reception->save();

        if (! empty($data['pallets'] ?? [])) {
            self::createPalletsFromRequest($reception, $data['pallets'], $data['prices'] ?? []);
        } else {
            self::createDetailsFromRequest($reception, $data['details'] ?? [], (int) $data['supplier']['id']);
        }

        return $reception;
    }

    /**
     * Actualizar una recepción existente.
     * Debe ejecutarse dentro de una transacción.
     *
     * @param  array  $data  Array validado (misma estructura que store según creation_mode)
     */
    public static function update(RawMaterialReception $reception, array $data): void
    {
        self::validateCanEdit($reception);

        $reception->update([
            'supplier_id' => $data['supplier']['id'],
            'date' => $data['date'],
            'notes' => $data['notes'] ?? null,
            'declared_total_amount' => $data['declaredTotalAmount'] ?? null,
            'declared_total_net_weight' => $data['declaredTotalNetWeight'] ?? null,
        ]);

        if ($reception->creation_mode === RawMaterialReception::CREATION_MODE_PALLETS) {
            self::updatePalletsFromRequest($reception, $data['pallets'] ?? [], $data['prices'] ?? []);
        } else {
            self::updateDetailsFromRequest($reception, $data['details'] ?? [], (int) $data['supplier']['id']);
        }
    }

    private static function updatePalletsFromRequest(RawMaterialReception $reception, array $palletsData, array $prices = []): void
    {
        $reception->load('pallets.boxes.box.productionInputs', 'products');
        $existingPallets = $reception->pallets->keyBy('id');
        $processedPalletIds = [];
        $groupedByProduct = [];
        $originalTotals = [];
        foreach ($reception->products as $receptionProduct) {
            $key = "{$receptionProduct->product_id}_{$receptionProduct->lot}";
            $originalTotals[$key] = [
                'product_id' => $receptionProduct->product_id,
                'lot' => $receptionProduct->lot,
                'net_weight' => $receptionProduct->net_weight,
                'price' => $receptionProduct->price,
            ];
        }
        $pricesMap = [];
        foreach ($prices as $priceData) {
            $productId = $priceData['product']['id'];
            $lot = $priceData['lot'];
            $key = "{$productId}_{$lot}";
            $pricesMap[$key] = $priceData['price'];
        }
        $hasUsedBoxes = false;
        foreach ($reception->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                    $hasUsedBoxes = true;
                    break 2;
                }
            }
        }
        foreach ($palletsData as $palletData) {
            $palletId = $palletData['id'] ?? null;
            $storeId = $palletData['store']['id'] ?? null;
            if ($palletId && $existingPallets->has($palletId)) {
                $pallet = $existingPallets->get($palletId);
                $pallet->observations = $palletData['observations'] ?? null;
                $pallet->status = $storeId ? Pallet::STATE_STORED : Pallet::STATE_REGISTERED;
                $pallet->save();
                $storedPallet = StoredPallet::where('pallet_id', $pallet->id)->first();
                if ($storeId) {
                    if ($storedPallet) {
                        if ($storedPallet->store_id != $storeId) {
                            $storedPallet->store_id = $storeId;
                            $storedPallet->save();
                        }
                    } else {
                        StoredPallet::create(['pallet_id' => $pallet->id, 'store_id' => $storeId]);
                    }
                } else {
                    if ($storedPallet) {
                        $storedPallet->delete();
                    }
                }
                $processedPalletIds[] = $palletId;
                $pallet->load('boxes.box.productionInputs');
            } else {
                if ($hasUsedBoxes) {
                    throw new \Exception("No se pueden crear nuevos palets cuando hay cajas siendo usadas en producción");
                }
                $pallet = new Pallet();
                $pallet->reception_id = $reception->id;
                $pallet->observations = $palletData['observations'] ?? null;
                $pallet->status = $storeId ? Pallet::STATE_STORED : Pallet::STATE_REGISTERED;
                $pallet->save();
                if ($storeId) {
                    StoredPallet::create(['pallet_id' => $pallet->id, 'store_id' => $storeId]);
                }
            }
            $existingBoxes = $pallet->boxes->keyBy(fn ($pb) => $pb->box_id);
            $processedBoxIds = [];
            foreach ($palletData['boxes'] as $boxData) {
                $boxId = $boxData['id'] ?? null;
                $productId = $boxData['product']['id'];
                $boxLot = $boxData['lot'] ?? self::generateLotFromReception($reception, $productId);
                if ($boxId && $existingBoxes->has($boxId)) {
                    $box = $existingBoxes->get($boxId)->box;
                    if (! $box) {
                        throw new \Exception("La caja #{$boxId} no existe");
                    }
                    $originalBox = Box::find($boxId);
                    if ($box->productionInputs()->exists()) {
                        if (isset($boxData['product']['id']) && $boxData['product']['id'] != $originalBox->article_id) {
                            throw new \Exception("No se puede modificar el producto de la caja #{$boxId}: está siendo usada en producción");
                        }
                        if (isset($boxData['lot']) && $boxData['lot'] != $originalBox->lot) {
                            throw new \Exception("No se puede modificar el lote de la caja #{$boxId}: está siendo usada en producción");
                        }
                        if (abs($boxData['netWeight'] - $originalBox->net_weight) > 0.01) {
                            throw new \Exception("No se puede modificar el peso neto de la caja #{$boxId}: está siendo usada en producción");
                        }
                        $processedBoxIds[] = $boxId;
                        continue;
                    }
                    if (isset($boxData['product']['id'])) {
                        $box->article_id = $boxData['product']['id'];
                    }
                    if (isset($boxData['lot'])) {
                        $box->lot = $boxData['lot'];
                    }
                    $box->net_weight = $boxData['netWeight'];
                    if (isset($boxData['grossWeight'])) {
                        $box->gross_weight = $boxData['grossWeight'];
                    }
                    if (isset($boxData['gs1128'])) {
                        $box->gs1_128 = $boxData['gs1128'];
                    }
                    $box->save();
                    $processedBoxIds[] = $boxId;
                    $key = "{$productId}_{$boxLot}";
                    if (! isset($groupedByProduct[$key])) {
                        $groupedByProduct[$key] = [
                            'product_id' => $productId,
                            'lot' => $boxLot,
                            'net_weight' => 0,
                            'price' => $pricesMap[$key] ?? self::getDefaultPrice($productId, $reception->supplier_id),
                        ];
                    }
                    $groupedByProduct[$key]['net_weight'] += $box->net_weight;
                } else {
                    if ($hasUsedBoxes) {
                        throw new \Exception("No se pueden crear nuevas cajas cuando hay cajas siendo usadas en producción");
                    }
                    $box = new Box();
                    $box->article_id = $productId;
                    $box->lot = $boxLot;
                    $box->gs1_128 = $boxData['gs1128'];
                    $box->gross_weight = $boxData['grossWeight'];
                    $box->net_weight = $boxData['netWeight'];
                    $box->save();
                    PalletBox::create(['pallet_id' => $pallet->id, 'box_id' => $box->id]);
                    $key = "{$productId}_{$boxLot}";
                    if (! isset($groupedByProduct[$key])) {
                        $groupedByProduct[$key] = [
                            'product_id' => $productId,
                            'lot' => $boxLot,
                            'net_weight' => 0,
                            'price' => $pricesMap[$key] ?? self::getDefaultPrice($productId, $reception->supplier_id),
                        ];
                    }
                    $groupedByProduct[$key]['net_weight'] += $box->net_weight;
                }
            }
            $boxesToDelete = $pallet->boxes->filter(fn ($pb) => ! in_array($pb->box_id, $processedBoxIds));
            foreach ($boxesToDelete as $palletBox) {
                $box = $palletBox->box;
                if ($box && $box->productionInputs()->exists()) {
                    throw new \Exception("No se puede eliminar la caja #{$box->id}: está siendo usada en producción");
                }
                $palletBox->box->delete();
                $palletBox->delete();
            }
            if ($boxesToDelete->isNotEmpty()) {
                $pallet->load('boxes.box.productionInputs');
            }
        }
        foreach ($reception->pallets as $pallet) {
            if (! in_array($pallet->id, $processedPalletIds)) {
                $pallet->load('boxes.box.productionInputs');
                $hasUsedBoxesInPallet = false;
                foreach ($pallet->boxes as $palletBox) {
                    if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                        $hasUsedBoxesInPallet = true;
                        break;
                    }
                }
                if ($hasUsedBoxesInPallet) {
                    throw new \Exception("No se puede eliminar el palet #{$pallet->id}: tiene cajas siendo usadas en producción");
                }
                foreach ($pallet->boxes as $palletBox) {
                    Box::find($palletBox->box_id)?->delete();
                }
                PalletBox::where('pallet_id', $pallet->id)->delete();
                Model::withoutEvents(fn () => $pallet->delete());
            }
        }
        $newTotals = [];
        foreach ($groupedByProduct as $key => $group) {
            $newTotals[$key] = ['product_id' => $group['product_id'], 'lot' => $group['lot'], 'net_weight' => $group['net_weight']];
        }
        foreach ($reception->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if ($box && $box->productionInputs()->exists()) {
                    $key = "{$box->article_id}_{$box->lot}";
                    if (! isset($newTotals[$key])) {
                        $newTotals[$key] = ['product_id' => $box->article_id, 'lot' => $box->lot, 'net_weight' => 0];
                    }
                    $newTotals[$key]['net_weight'] += $box->net_weight;
                }
            }
        }
        $tolerance = 0.01;
        $adjustments = [];
        foreach ($originalTotals as $key => $original) {
            if (! isset($newTotals[$key])) {
                if ($hasUsedBoxes) {
                    throw new \Exception("El producto {$original['product_id']} con lote {$original['lot']} ya no tiene cajas. No se pueden eliminar todos los productos cuando hay cajas usadas.");
                }
                continue;
            }
            $difference = $original['net_weight'] - $newTotals[$key]['net_weight'];
            if ($hasUsedBoxes && abs($difference) > $tolerance) {
                throw new \Exception("El total del producto {$original['product_id']} con lote {$original['lot']} ha cambiado.");
            }
            if (abs($difference) > 0 && abs($difference) <= $tolerance) {
                $adjustments[$key] = ['product_id' => $original['product_id'], 'lot' => $original['lot'], 'difference' => $difference];
            }
        }
        if ($hasUsedBoxes) {
            foreach ($newTotals as $key => $new) {
                if (! isset($originalTotals[$key])) {
                    throw new \Exception("Se ha agregado un nuevo producto {$new['product_id']} con lote {$new['lot']}. No se pueden agregar nuevos productos cuando hay cajas usadas.");
                }
            }
        }
        foreach ($adjustments as $key => $adjustment) {
            $lastBox = null;
            foreach ($reception->pallets as $pallet) {
                foreach ($pallet->boxes as $palletBox) {
                    $box = $palletBox->box;
                    if ($box && $box->article_id == $adjustment['product_id'] && $box->lot == $adjustment['lot'] && ! $box->productionInputs()->exists()) {
                        $lastBox = $box;
                    }
                }
            }
            if ($lastBox) {
                $lastBox->net_weight += $adjustment['difference'];
                $lastBox->save();
            }
        }
        $reception->products()->delete();
        $reception->load('pallets.boxes.box.productionInputs');
        $finalTotals = [];
        foreach ($reception->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (! $box) {
                    continue;
                }
                $key = "{$box->article_id}_{$box->lot}";
                if (! isset($finalTotals[$key])) {
                    $finalTotals[$key] = ['product_id' => $box->article_id, 'lot' => $box->lot, 'net_weight' => 0];
                }
                $finalTotals[$key]['net_weight'] += $box->net_weight;
            }
        }
        foreach ($finalTotals as $key => $total) {
            $price = $hasUsedBoxes && isset($originalTotals[$key])
                ? $originalTotals[$key]['price']
                : ($pricesMap[$key] ?? self::getDefaultPrice($total['product_id'], $reception->supplier_id));
            $reception->products()->create([
                'product_id' => $total['product_id'],
                'lot' => $total['lot'],
                'net_weight' => $total['net_weight'],
                'price' => $price,
            ]);
        }
    }

    private static function createPalletsFromRequest(RawMaterialReception $reception, array $pallets, array $prices = []): void
    {
        $pricesMap = [];
        foreach ($prices as $priceData) {
            $key = "{$priceData['product']['id']}_{$priceData['lot']}";
            $pricesMap[$key] = $priceData['price'];
        }
        foreach ($pallets as $palletData) {
            $pallet = new Pallet();
            $pallet->reception_id = $reception->id;
            $pallet->observations = $palletData['observations'] ?? null;
            $storeId = $palletData['store']['id'] ?? null;
            $pallet->status = $storeId ? Pallet::STATE_STORED : Pallet::STATE_REGISTERED;
            $pallet->save();
            if ($storeId) {
                StoredPallet::create(['pallet_id' => $pallet->id, 'store_id' => $storeId]);
            }
            foreach ($palletData['boxes'] as $boxData) {
                $productId = $boxData['product']['id'];
                $boxLot = $boxData['lot'] ?? self::generateLotFromReception($reception, $productId);
                $box = new Box();
                $box->article_id = $productId;
                $box->lot = $boxLot;
                $box->gs1_128 = $boxData['gs1128'];
                $box->gross_weight = $boxData['grossWeight'];
                $box->net_weight = $boxData['netWeight'];
                $box->save();
                PalletBox::create(['pallet_id' => $pallet->id, 'box_id' => $box->id]);
            }
        }
        $reception->load('pallets.boxes.box');
        $finalTotals = [];
        foreach ($reception->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (! $box) {
                    continue;
                }
                $key = "{$box->article_id}_{$box->lot}";
                if (! isset($finalTotals[$key])) {
                    $finalTotals[$key] = ['product_id' => $box->article_id, 'lot' => $box->lot, 'net_weight' => 0];
                }
                $finalTotals[$key]['net_weight'] += $box->net_weight;
            }
        }
        foreach ($finalTotals as $key => $total) {
            $reception->products()->create([
                'product_id' => $total['product_id'],
                'lot' => $total['lot'],
                'net_weight' => $total['net_weight'],
                'price' => $pricesMap[$key] ?? self::getDefaultPrice($total['product_id'], $reception->supplier_id),
            ]);
        }
    }

    private static function updateDetailsFromRequest(RawMaterialReception $reception, array $details, int $supplierId): void
    {
        $reception->load('pallets.boxes.box');
        $pallet = $reception->pallets->first();
        if (! $pallet) {
            $pallet = new Pallet();
            $pallet->reception_id = $reception->id;
            $pallet->observations = "Auto-generado desde recepción #{$reception->id}";
            $pallet->status = Pallet::STATE_REGISTERED;
            $pallet->save();
        } else {
            $pallet->observations = "Auto-generado desde recepción #{$reception->id}";
            $pallet->save();
            $pallet->load('boxes.box.productionInputs');
            foreach ($pallet->boxes as $palletBox) {
                if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                    throw new \Exception("RECEPTION_LINES_MODE: No se puede modificar la recepción porque hay materia prima siendo usada en producción");
                }
            }
            foreach ($pallet->boxes as $palletBox) {
                Box::find($palletBox->box_id)?->delete();
            }
            PalletBox::where('pallet_id', $pallet->id)->delete();
        }
        $reception->products()->delete();
        foreach ($details as $detail) {
            $productId = $detail['product']['id'];
            $price = $detail['price'] ?? self::getDefaultPrice($productId, $supplierId);
            $lot = $detail['lot'] ?? self::generateLotFromReception($reception, $productId);
            $reception->products()->create([
                'product_id' => $productId,
                'lot' => $lot,
                'net_weight' => $detail['netWeight'],
                'price' => $price,
            ]);
            $numBoxes = max(1, $detail['boxes'] ?? 1);
            $totalWeight = $detail['netWeight'];
            $weightPerBox = round($totalWeight / $numBoxes, 2);
            $accumulatedWeight = $weightPerBox * ($numBoxes - 1);
            $lastBoxWeight = $totalWeight - $accumulatedWeight;
            for ($i = 0; $i < $numBoxes; $i++) {
                $box = new Box();
                $box->article_id = $productId;
                $box->lot = $lot;
                $box->gs1_128 = self::generateGS1128($reception, $productId, $i);
                $box->gross_weight = round((($i === $numBoxes - 1) ? $lastBoxWeight : $weightPerBox) * 1.02, 2);
                $box->net_weight = ($i === $numBoxes - 1) ? $lastBoxWeight : $weightPerBox;
                $box->save();
                PalletBox::create(['pallet_id' => $pallet->id, 'box_id' => $box->id]);
            }
        }
    }

    private static function createDetailsFromRequest(RawMaterialReception $reception, array $details, int $supplierId): void
    {
        $pallet = new Pallet();
        $pallet->reception_id = $reception->id;
        $pallet->observations = "Auto-generado desde recepción #{$reception->id}";
        $pallet->status = Pallet::STATE_REGISTERED;
        $pallet->save();
        foreach ($details as $detail) {
            $productId = $detail['product']['id'];
            $price = $detail['price'] ?? self::getDefaultPrice($productId, $supplierId);
            $lot = $detail['lot'] ?? self::generateLotFromReception($reception, $productId);
            $reception->products()->create([
                'product_id' => $productId,
                'lot' => $lot,
                'net_weight' => $detail['netWeight'],
                'price' => $price,
            ]);
            $numBoxes = max(1, $detail['boxes'] ?? 1);
            $totalWeight = $detail['netWeight'];
            $weightPerBox = round($totalWeight / $numBoxes, 2);
            $lastBoxWeight = $totalWeight - $weightPerBox * ($numBoxes - 1);
            for ($i = 0; $i < $numBoxes; $i++) {
                $box = new Box();
                $box->article_id = $productId;
                $box->lot = $lot;
                $box->gs1_128 = self::generateGS1128($reception, $productId, $i);
                $box->gross_weight = round((($i === $numBoxes - 1) ? $lastBoxWeight : $weightPerBox) * 1.02, 2);
                $box->net_weight = ($i === $numBoxes - 1) ? $lastBoxWeight : $weightPerBox;
                $box->save();
                PalletBox::create(['pallet_id' => $pallet->id, 'box_id' => $box->id]);
            }
        }
    }

    private static function getDefaultPrice(int $productId, int $supplierId): ?float
    {
        $lastReception = RawMaterialReception::where('supplier_id', $supplierId)
            ->whereHas('products', fn ($q) => $q->where('product_id', $productId)->whereNotNull('price'))
            ->orderBy('date', 'desc')
            ->first();
        if ($lastReception) {
            $lastProduct = $lastReception->products()
                ->where('product_id', $productId)
                ->whereNotNull('price')
                ->orderBy('created_at', 'desc')
                ->first();
            return $lastProduct?->price;
        }
        return null;
    }

    private static function generateLotFromReception(RawMaterialReception $reception, int $productId): string
    {
        $product = Product::with(['species', 'captureZone'])->find($productId);
        if (! $product || ! $product->species || ! $product->capture_zone_id) {
            return date('Ymd', strtotime($reception->date)).'-'.$reception->id.'-'.$productId;
        }
        $date = strtotime($reception->date);
        $faoCode = $product->species->fao ?? '';
        $captureZoneId = str_pad((string) $product->capture_zone_id, 2, '0', STR_PAD_LEFT);
        return date('d', $date).date('m', $date).date('y', $date).$faoCode.$captureZoneId.'REC';
    }

    private static function generateGS1128(RawMaterialReception $reception, int $productId, int $index = 0): string
    {
        return 'GS1-'.$reception->id.'-'.$productId.'-'.$index.'-'.time();
    }

    private static function validateCanEdit(RawMaterialReception $reception): void
    {
        if (! $reception->relationLoaded('pallets')) {
            $reception->load('pallets.reception', 'pallets.boxes.box.productionInputs');
        }
        foreach ($reception->pallets as $pallet) {
            if ($pallet->order_id !== null) {
                throw new \Exception("No se puede modificar la recepción: el palet #{$pallet->id} está vinculado a un pedido");
            }
        }
    }
}
