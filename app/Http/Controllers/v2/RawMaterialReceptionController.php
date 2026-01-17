<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\RawMaterialReceptionResource;
use App\Models\RawMaterial;
use App\Models\RawMaterialReception;
use App\Models\RawMaterialReceptionProduct;
use App\Models\Pallet;
use App\Models\Box;
use App\Models\PalletBox;
use App\Models\StoredPallet;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class RawMaterialReceptionController extends Controller
{
    public function index(Request $request)
    {
        $query = RawMaterialReception::query();
        $query->with('supplier', 'products.product', 'pallets.reception', 'pallets.boxes.box.productionInputs');

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        /* ids */
        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        if ($request->has('suppliers')) {
            $query->whereIn('supplier_id', $request->suppliers);
        }

        if ($request->has('dates')) {
            $dates = $request->input('dates');
            /* Check if $dates['start'] exists */
            if (isset($dates['start'])) {
                $startDate = $dates['start'];
                $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                $query->where('date', '>=', $startDate);
            }
            /* Check if $dates['end'] exists */
            if (isset($dates['end'])) {
                $endDate = $dates['end'];
                $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                $query->where('date', '<=', $endDate);
            }
        }

        if ($request->has('species')) {
            $query->whereHas('products.product', function ($query) use ($request) {
                $query->whereIn('species_id', $request->species);
            });
        }

        if ($request->has('products')) {
            $query->whereHas('products.product', function ($query) use ($request) {
                $query->whereIn('id', $request->products);
            });
        }

        if ($request->has('notes')) {
            $query->where('notes', 'like', '%' . $request->notes . '%');
        }

        /* Order by Date Descen */
        $query->orderBy('date', 'desc');

        $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
        return RawMaterialReceptionResource::collection($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier.id' => 'required',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'declaredTotalAmount' => 'nullable|numeric|min:0',
            'declaredTotalNetWeight' => 'nullable|numeric|min:0',
            // Opción 1: Líneas con creación automática de palets
            'details' => 'required_without:pallets|array',
            'details.*.product.id' => 'required_with:details|exists:tenant.products,id',
            'details.*.netWeight' => 'required_with:details|numeric',
            'details.*.price' => 'nullable|numeric|min:0',
            'details.*.lot' => 'nullable|string',
            'details.*.boxes' => 'nullable|integer|min:0', // Número de cajas (0 = 1)
            // Opción 2: Palets manuales con creación automática de líneas
            'pallets' => 'required_without:details|array',
            'pallets.*.observations' => 'nullable|string',
            'pallets.*.store.id' => 'nullable|integer|exists:tenant.stores,id',
            'pallets.*.boxes' => 'required_with:pallets|array|min:1',
            'pallets.*.boxes.*.product.id' => 'required|exists:tenant.products,id',
            'pallets.*.boxes.*.lot' => 'nullable|string',
            'pallets.*.boxes.*.gs1128' => 'required|string',
            'pallets.*.boxes.*.grossWeight' => 'required|numeric',
            'pallets.*.boxes.*.netWeight' => 'required|numeric',
            // Precios en la raíz de la recepción (compartidos por todos los palets)
            'prices' => 'required_with:pallets|array',
            'prices.*.product.id' => 'required_with:prices|exists:tenant.products,id',
            'prices.*.lot' => 'required_with:prices|string',
            'prices.*.price' => 'required_with:prices|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request) {
            // 1. Crear recepción
            $reception = new RawMaterialReception();
            $reception->supplier_id = $request->supplier['id'];
            $reception->date = $request->date;
            $reception->notes = $request->notes ?? null;
            $reception->declared_total_amount = $request->declaredTotalAmount ?? null;
            $reception->declared_total_net_weight = $request->declaredTotalNetWeight ?? null;
            
            // Determinar y guardar el modo de creación
            if ($request->has('pallets') && !empty($request->pallets)) {
                $reception->creation_mode = RawMaterialReception::CREATION_MODE_PALLETS;
            } else {
                $reception->creation_mode = RawMaterialReception::CREATION_MODE_LINES;
            }
            
            $reception->save();

            // 2. Crear palets y líneas según el modo
            if ($request->has('pallets') && !empty($request->pallets)) {
                // Modo manual: crear palets y generar líneas
                $prices = $request->prices ?? [];
                $this->createPalletsFromRequest($reception, $request->pallets, $prices);
            } else {
                // Modo automático: crear líneas y generar palet
                $this->createDetailsFromRequest($reception, $request->details, $request->supplier['id']);
            }

            // 3. Cargar relaciones para respuesta
            $reception->load('supplier', 'products.product', 'pallets.reception', 'pallets.boxes.box.productionInputs');
      
            return response()->json([
                'message' => 'Recepción de materia prima creada correctamente.',
                'data' => new RawMaterialReceptionResource($reception),
            ], 201);
        });
    }

    public function show($id)
    {
        $reception = RawMaterialReception::with('supplier', 'products.product', 'pallets.reception', 'pallets.boxes.box.productionInputs')->findOrFail($id);
        return response()->json([
            'data' => new RawMaterialReceptionResource($reception),
        ]);
    }

    public function update(Request $request, $id)
    {
        $reception = RawMaterialReception::with('pallets.reception', 'pallets.boxes.box.productionInputs')->findOrFail($id);

        // Validar según el modo de creación
        if ($reception->creation_mode === RawMaterialReception::CREATION_MODE_LINES) {
            $validated = $request->validate([
                'supplier.id' => 'required',
                'date' => 'required|date',
                'notes' => 'nullable|string',
                'declaredTotalAmount' => 'nullable|numeric|min:0',
                'declaredTotalNetWeight' => 'nullable|numeric|min:0',
                'details' => 'required|array',
                'details.*.product.id' => 'required|exists:tenant.products,id',
                'details.*.netWeight' => 'required|numeric',
                'details.*.price' => 'nullable|numeric|min:0',
                'details.*.lot' => 'nullable|string',
                'details.*.boxes' => 'nullable|integer|min:0',
            ]);
        } elseif ($reception->creation_mode === RawMaterialReception::CREATION_MODE_PALLETS) {
            $validated = $request->validate([
                'supplier.id' => 'required',
                'date' => 'required|date',
                'notes' => 'nullable|string',
                'declaredTotalAmount' => 'nullable|numeric|min:0',
                'declaredTotalNetWeight' => 'nullable|numeric|min:0',
                'pallets' => 'required|array',
                'pallets.*.id' => 'nullable|integer|exists:tenant.pallets,id',
                'pallets.*.observations' => 'nullable|string',
                'pallets.*.store.id' => 'nullable|integer|exists:tenant.stores,id',
                'pallets.*.boxes' => 'required|array|min:1',
                'pallets.*.boxes.*.id' => 'nullable|integer|exists:tenant.boxes,id',
                'pallets.*.boxes.*.product.id' => 'required|exists:tenant.products,id',
                'pallets.*.boxes.*.lot' => 'nullable|string',
                'pallets.*.boxes.*.gs1128' => 'required|string',
                'pallets.*.boxes.*.grossWeight' => 'required|numeric',
                'pallets.*.boxes.*.netWeight' => 'required|numeric',
                // Precios en la raíz de la recepción (compartidos por todos los palets)
                'prices' => 'required|array',
                'prices.*.product.id' => 'required|exists:tenant.products,id',
                'prices.*.lot' => 'required|string',
                'prices.*.price' => 'required|numeric|min:0',
            ]);
        } else {
            // Recepciones antiguas sin creation_mode - permitir edición por líneas
            $validated = $request->validate([
                'supplier.id' => 'required',
                'date' => 'required|date',
                'notes' => 'nullable|string',
                'declaredTotalAmount' => 'nullable|numeric|min:0',
                'declaredTotalNetWeight' => 'nullable|numeric|min:0',
                'details' => 'required|array',
                'details.*.product.id' => 'required|exists:tenant.products,id',
                'details.*.netWeight' => 'required|numeric',
                'details.*.price' => 'nullable|numeric|min:0',
                'details.*.lot' => 'nullable|string',
                'details.*.boxes' => 'nullable|integer|min:0',
            ]);
        }

        return DB::transaction(function () use ($reception, $validated, $request) {
            // Validar restricciones comunes (cajas en producción, palets vinculados)
            $this->validateCanEdit($reception);
            
            // Actualizar recepción
            $reception->update([
                'supplier_id' => $validated['supplier']['id'],
                'date' => $validated['date'],
                'notes' => $validated['notes'] ?? null,
                'declared_total_amount' => $validated['declaredTotalAmount'] ?? null,
                'declared_total_net_weight' => $validated['declaredTotalNetWeight'] ?? null,
            ]);

            // Actualizar datos según el modo (editar en lugar de eliminar/recrear)
            if ($reception->creation_mode === RawMaterialReception::CREATION_MODE_PALLETS) {
                // Modo palets: editar palets y cajas existentes
                $prices = $validated['prices'] ?? [];
                $this->updatePalletsFromRequest($reception, $validated['pallets'], $prices);
            } else {
                // Modo líneas o recepciones antiguas: editar palet único y cajas
                $this->updateDetailsFromRequest($reception, $validated['details'], $request->supplier['id']);
            }

            $reception->load('supplier', 'products.product', 'pallets.reception', 'pallets.boxes.box.productionInputs');
            return response()->json([
                'message' => 'Recepción de materia prima actualizada correctamente.',
                'data' => new RawMaterialReceptionResource($reception),
            ]);
        });
    }

    public function destroy($id)
    {
        $reception = RawMaterialReception::findOrFail($id);
        $reception->delete();
        return response()->json(['message' => 'Recepción eliminada correctamente'], 200);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json([
                'message' => 'No se han proporcionado IDs válidos.',
                'userMessage' => 'Debe proporcionar al menos un ID válido para eliminar.'
            ], 400);
        }

        RawMaterialReception::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Recepciones de materia prima eliminadas con éxito']);
    }

    /**
     * Actualizar palets desde request (modo manual - edición)
     * 
     * Permite editar solo cajas disponibles cuando hay cajas usadas en producción,
     * manteniendo los totales por producto y generales exactamente iguales.
     */
    private function updatePalletsFromRequest(RawMaterialReception $reception, array $palletsData, array $prices = []): void
    {
        // Cargar palets existentes con sus cajas y productionInputs
        $reception->load('pallets.boxes.box.productionInputs', 'products');
        $existingPallets = $reception->pallets->keyBy('id');
        $processedPalletIds = [];
        $groupedByProduct = [];

        // 1. Obtener totales originales por producto+lote
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

        // Crear mapa de precios por producto+lote (compartido por todos los palets)
        $pricesMap = [];
        foreach ($prices as $priceData) {
            $productId = $priceData['product']['id'];
            $lot = $priceData['lot'];
            $key = "{$productId}_{$lot}";
            $pricesMap[$key] = $priceData['price'];
        }

        // 2. Verificar si hay cajas usadas en algún palet
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
            
            // Determinar si es palet existente o nuevo
            $palletId = $palletData['id'] ?? null;
            
            // Obtener almacén del request
            $storeId = $palletData['store']['id'] ?? null;
            
            if ($palletId && $existingPallets->has($palletId)) {
                // Actualizar palet existente
                $pallet = $existingPallets->get($palletId);
                $pallet->observations = $palletData['observations'] ?? null;
                
                // Actualizar estado según almacén
                if ($storeId) {
                    $pallet->status = Pallet::STATE_STORED; // Almacenado
                } else {
                    $pallet->status = Pallet::STATE_REGISTERED; // Registrado
                }
                
                $pallet->save();
                
                // Actualizar almacenamiento
                $storedPallet = StoredPallet::where('pallet_id', $pallet->id)->first();
                if ($storeId) {
                    if ($storedPallet) {
                        // Actualizar almacén existente
                        if ($storedPallet->store_id != $storeId) {
                            $storedPallet->store_id = $storeId;
                            $storedPallet->save();
                        }
                    } else {
                        // Crear nuevo almacenamiento
                        StoredPallet::create([
                            'pallet_id' => $pallet->id,
                            'store_id' => $storeId,
                        ]);
                    }
                } else {
                    // Eliminar almacenamiento si ya no hay almacén
                    if ($storedPallet) {
                        $storedPallet->delete();
                    }
                }
                
                $processedPalletIds[] = $palletId;
                // Recargar cajas del palet con productionInputs
                $pallet->load('boxes.box.productionInputs');
            } else {
                // ✅ NUEVO: Si hay cajas usadas, no permitir crear nuevos palets
                if ($hasUsedBoxes) {
                    throw new \Exception("No se pueden crear nuevos palets cuando hay cajas siendo usadas en producción");
                }
                
                // Crear nuevo palet
                $pallet = new Pallet();
                $pallet->reception_id = $reception->id;
                $pallet->observations = $palletData['observations'] ?? null;
                
                // Determinar estado según si se indica almacén
                if ($storeId) {
                    $pallet->status = Pallet::STATE_STORED; // Almacenado
                } else {
                    $pallet->status = Pallet::STATE_REGISTERED; // Registrado
                }
                
                $pallet->save();
                $palletId = $pallet->id;
                
                // Crear vínculo con almacén si se proporciona
                if ($storeId) {
                    StoredPallet::create([
                        'pallet_id' => $pallet->id,
                        'store_id' => $storeId,
                    ]);
                }
            }

            // Procesar cajas del palet
            $existingBoxes = $pallet->boxes->keyBy(function ($palletBox) {
                return $palletBox->box_id;
            });
            
            $processedBoxIds = [];

            foreach ($palletData['boxes'] as $boxData) {
                $boxId = $boxData['id'] ?? null;
                $productId = $boxData['product']['id'];
                // Tomar el lote de la caja, si no tiene generar uno automáticamente
                $boxLot = $boxData['lot'] ?? $this->generateLotFromReception($reception, $productId);
                
                if ($boxId && $existingBoxes->has($boxId)) {
                    // ✅ NUEVO: Validar que la caja existe y está disponible
                    $box = $existingBoxes->get($boxId)->box;
                    
                    if (!$box) {
                        throw new \Exception("La caja #{$boxId} no existe");
                    }
                    
                    // Verificar que la caja pertenece al palet
                    $palletBox = PalletBox::where('pallet_id', $pallet->id)
                        ->where('box_id', $boxId)
                        ->first();
                    if (!$palletBox) {
                        throw new \Exception("La caja #{$boxId} no pertenece al palet #{$pallet->id}");
                    }
                    
                    // ✅ NUEVO: Validar que la caja está disponible (no usada en producción)
                    // Cargar caja original para comparar
                    $originalBox = Box::find($boxId);
                    
                    if ($box->productionInputs()->exists()) {
                        // Si la caja está usada en producción, no se puede modificar NADA
                        $hasChanges = false;
                        $errorMessage = "No se puede modificar la caja #{$boxId}: está siendo usada en producción";
                        
                        if (isset($boxData['product']['id']) && $boxData['product']['id'] != $originalBox->article_id) {
                            throw new \Exception("No se puede modificar el producto de la caja #{$boxId}: está siendo usada en producción");
                        }
                        if (isset($boxData['lot']) && $boxData['lot'] != $originalBox->lot) {
                            throw new \Exception("No se puede modificar el lote de la caja #{$boxId}: está siendo usada en producción");
                        }
                        if (abs($boxData['netWeight'] - $originalBox->net_weight) > 0.01) {
                            throw new \Exception("No se puede modificar el peso neto de la caja #{$boxId}: está siendo usada en producción");
                        }
                        if (isset($boxData['grossWeight']) && abs($boxData['grossWeight'] - $originalBox->gross_weight) > 0.01) {
                            throw new \Exception("No se puede modificar el peso bruto de la caja #{$boxId}: está siendo usada en producción");
                        }
                        if (isset($boxData['gs1128']) && $boxData['gs1128'] != $originalBox->gs1_128) {
                            throw new \Exception("No se puede modificar el GS1-128 de la caja #{$boxId}: está siendo usada en producción");
                        }
                        
                        // Si no hay cambios, permitir que esté en el request (solo para cálculo de totales)
                        // No la procesamos, pero la incluiremos en los totales
                        $processedBoxIds[] = $boxId;
                        continue;
                    }
                    
                    // ✅ Si la caja NO está en producción, permitir modificar todos los campos
                    // Actualizar producto si se proporciona
                    if (isset($boxData['product']['id'])) {
                        $box->article_id = $boxData['product']['id'];
                    }
                    
                    // Actualizar lote si se proporciona
                    if (isset($boxData['lot'])) {
                        $box->lot = $boxData['lot'];
                    }
                    
                    // Actualizar peso neto
                    $box->net_weight = $boxData['netWeight'];
                    
                    // Actualizar peso bruto si se proporciona
                    if (isset($boxData['grossWeight'])) {
                        $box->gross_weight = $boxData['grossWeight'];
                    }
                    
                    // Actualizar GS1-128 si se proporciona
                    if (isset($boxData['gs1128'])) {
                        $box->gs1_128 = $boxData['gs1128'];
                    }
                    
                    $box->save();
                    $processedBoxIds[] = $boxId;
                    
                    // Agrupar por producto y lote (caja disponible modificada)
                    $key = "{$productId}_{$boxLot}";
                    if (!isset($groupedByProduct[$key])) {
                        // Buscar precio en el mapa de precios, si no existe buscar del histórico
                        $price = $pricesMap[$key] ?? $this->getDefaultPrice($productId, $reception->supplier_id);
                        
                        $groupedByProduct[$key] = [
                            'product_id' => $productId,
                            'lot' => $boxLot,
                            'net_weight' => 0,
                            'price' => $price,
                        ];
                    }
                    $groupedByProduct[$key]['net_weight'] += $box->net_weight;
                } else {
                    // ✅ NUEVO: Si hay cajas usadas, no permitir crear nuevas cajas
                    if ($hasUsedBoxes) {
                        throw new \Exception("No se pueden crear nuevas cajas cuando hay cajas siendo usadas en producción");
                    }
                    
                    // Crear nueva caja
                    $box = new Box();
                    $box->article_id = $productId;
                    $box->lot = $boxLot;
                    $box->gs1_128 = $boxData['gs1128'];
                    $box->gross_weight = $boxData['grossWeight'];
                    $box->net_weight = $boxData['netWeight'];
                    $box->save();
                    
                    // Crear relación palet-caja
                    PalletBox::create([
                        'pallet_id' => $pallet->id,
                        'box_id' => $box->id,
                    ]);
                    
                    // Agrupar por producto y lote (nueva caja, siempre disponible)
                    $key = "{$productId}_{$boxLot}";
                    if (!isset($groupedByProduct[$key])) {
                        // Buscar precio en el mapa de precios, si no existe buscar del histórico
                        $price = $pricesMap[$key] ?? $this->getDefaultPrice($productId, $reception->supplier_id);
                        
                        $groupedByProduct[$key] = [
                            'product_id' => $productId,
                            'lot' => $boxLot,
                            'net_weight' => 0,
                            'price' => $price,
                        ];
                    }
                    $groupedByProduct[$key]['net_weight'] += $box->net_weight;
                }
            }

            // ✅ NUEVO: Eliminar cajas que ya no están en el request (solo si están disponibles)
            $boxesToDelete = $pallet->boxes->filter(function ($palletBox) use ($processedBoxIds) {
                return !in_array($palletBox->box_id, $processedBoxIds);
            });
            
            foreach ($boxesToDelete as $palletBox) {
                $box = $palletBox->box;
                
                // ✅ NUEVO: No eliminar si está en producción
                if ($box && $box->productionInputs()->exists()) {
                    throw new \Exception("No se puede eliminar la caja #{$box->id}: está siendo usada en producción");
                }
                
                // Eliminar caja disponible
                $palletBox->box->delete();
                $palletBox->delete();
            }
            
            // Recargar relación después de eliminar
            if ($boxesToDelete->isNotEmpty()) {
                $pallet->load('boxes.box.productionInputs');
            }
        }

        // ✅ NUEVO: Eliminar palets que ya no están en el request (solo si no tienen cajas usadas)
        foreach ($reception->pallets as $pallet) {
            if (!in_array($pallet->id, $processedPalletIds)) {
                // Cargar cajas del palet con productionInputs
                $pallet->load('boxes.box.productionInputs');
                
                // ✅ NUEVO: Verificar si tiene cajas en producción
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
                
                // Eliminar relaciones palet-caja y cajas (usando eliminación directa de BD)
                foreach ($pallet->boxes as $palletBox) {
                    DB::connection('tenant')->table('boxes')->where('id', $palletBox->box_id)->delete();
                }
                DB::connection('tenant')->table('pallet_boxes')->where('pallet_id', $pallet->id)->delete();
                // Eliminar palet (usando eliminación directa de BD para evitar el evento)
                DB::connection('tenant')->table('pallets')->where('id', $pallet->id)->delete();
            }
        }

        // 3. Calcular totales nuevos (incluyendo cajas usadas que no están en el request)
        $newTotals = [];
        
        // Obtener IDs de todas las cajas procesadas (disponibles y usadas que están en el request)
        $allProcessedBoxIds = [];
        foreach ($palletsData as $palletData) {
            foreach ($palletData['boxes'] as $boxData) {
                if (isset($boxData['id'])) {
                    $allProcessedBoxIds[] = $boxData['id'];
                }
            }
        }
        
        // Primero sumar todas las cajas del request (solo disponibles, las usadas se incluyen después)
        foreach ($groupedByProduct as $key => $group) {
            $newTotals[$key] = [
                'product_id' => $group['product_id'],
                'lot' => $group['lot'],
                'net_weight' => $group['net_weight'],
            ];
        }
        
        // Luego incluir cajas usadas (que no están en el request o que están pero no se modificaron)
        foreach ($reception->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if ($box && $box->productionInputs()->exists()) {
                    // Incluir todas las cajas usadas (estén o no en el request, tienen el mismo peso)
                    $key = "{$box->article_id}_{$box->lot}";
                    if (!isset($newTotals[$key])) {
                        $newTotals[$key] = [
                            'product_id' => $box->article_id,
                            'lot' => $box->lot,
                            'net_weight' => 0,
                        ];
                    }
                    $newTotals[$key]['net_weight'] += $box->net_weight;
                }
            }
        }

        // 4. Validar que los totales coincidan (con tolerancia de redondeos)
        // Solo validar restricciones estrictas si hay cajas usadas
        $tolerance = 0.01; // 0.01 kg de tolerancia
        $adjustments = []; // Guardar ajustes necesarios por producto
        
        foreach ($originalTotals as $key => $original) {
            if (!isset($newTotals[$key])) {
                // Solo bloquear eliminación de productos si hay cajas usadas
                if ($hasUsedBoxes) {
                    throw new \Exception(
                        "El producto {$original['product_id']} con lote {$original['lot']} ya no tiene cajas. " .
                        "No se pueden eliminar todos los productos cuando hay cajas usadas."
                    );
                }
                // Si no hay cajas usadas, permitir eliminar productos
                continue;
            }
            
            $difference = $original['net_weight'] - $newTotals[$key]['net_weight'];
            
            // Solo validar que los totales coincidan exactamente si hay cajas usadas
            if ($hasUsedBoxes && abs($difference) > $tolerance) {
                throw new \Exception(
                    "El total del producto {$original['product_id']} con lote {$original['lot']} ha cambiado. " .
                    "Original: {$original['net_weight']} kg, Nuevo: {$newTotals[$key]['net_weight']} kg, " .
                    "Diferencia: " . abs($difference) . " kg"
                );
            }
            
            // Guardar ajuste si hay diferencia pequeña por redondeos
            if (abs($difference) > 0 && abs($difference) <= $tolerance) {
                $adjustments[$key] = [
                    'product_id' => $original['product_id'],
                    'lot' => $original['lot'],
                    'difference' => $difference,
                ];
            }
        }
        
        // Verificar que no haya productos nuevos que no existían antes
        // Solo bloquear si hay cajas usadas
        if ($hasUsedBoxes) {
            foreach ($newTotals as $key => $new) {
                if (!isset($originalTotals[$key])) {
                    throw new \Exception(
                        "Se ha agregado un nuevo producto {$new['product_id']} con lote {$new['lot']}. " .
                        "No se pueden agregar nuevos productos cuando hay cajas usadas."
                    );
                }
            }
        }

        // 5. Ajustar automáticamente diferencias pequeñas por redondeos
        foreach ($adjustments as $key => $adjustment) {
            $productId = $adjustment['product_id'];
            $lot = $adjustment['lot'];
            $difference = $adjustment['difference'];
            
            // Buscar la última caja disponible del producto en el palet que se procesó
            $lastBox = null;
            foreach ($reception->pallets as $pallet) {
                foreach ($pallet->boxes as $palletBox) {
                    $box = $palletBox->box;
                    if ($box && 
                        $box->article_id == $productId && 
                        $box->lot == $lot && 
                        !$box->productionInputs()->exists()) {
                        $lastBox = $box;
                    }
                }
            }
            
            if ($lastBox) {
                // Ajustar el peso de la última caja para cuadrar
                $lastBox->net_weight += $difference;
                $lastBox->save();
                
                // Actualizar el total en groupedByProduct
                $groupedByProduct[$key]['net_weight'] += $difference;
            }
        }

        // 6. Eliminar líneas antiguas y crear nuevas (manteniendo precios originales si hay cajas usadas)
        // ✅ CORREGIDO: Usar $newTotals que incluye todas las cajas (disponibles + usadas)
        $reception->products()->delete();
        
        // Recargar palets para obtener los datos actualizados después de los ajustes
        $reception->load('pallets.boxes.box.productionInputs');
        
        // Recalcular totales finales desde todas las cajas (después de ajustes)
        $finalTotals = [];
        foreach ($reception->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (!$box) {
                    continue;
                }
                
                $key = "{$box->article_id}_{$box->lot}";
                if (!isset($finalTotals[$key])) {
                    $finalTotals[$key] = [
                        'product_id' => $box->article_id,
                        'lot' => $box->lot,
                        'net_weight' => 0,
                    ];
                }
                $finalTotals[$key]['net_weight'] += $box->net_weight;
            }
        }
        
        // Crear líneas de recepción con los totales finales
        foreach ($finalTotals as $key => $total) {
            // Obtener precio: mantener original si hay cajas usadas, sino usar del request
            $price = null;
            if ($hasUsedBoxes && isset($originalTotals[$key])) {
                $price = $originalTotals[$key]['price'];
            } else {
                // Buscar precio en el mapa de precios o del histórico
                $price = $pricesMap[$key] ?? $this->getDefaultPrice($total['product_id'], $reception->supplier_id);
            }
            
            $reception->products()->create([
                'product_id' => $total['product_id'],
                'lot' => $total['lot'],
                'net_weight' => $total['net_weight'],
                'price' => $price,
            ]);
        }
    }

    /**
     * Crear palets desde request (modo manual)
     */
    private function createPalletsFromRequest(RawMaterialReception $reception, array $pallets, array $prices = []): void
    {
        // Crear mapa de precios por producto+lote (compartido por todos los palets)
        $pricesMap = [];
        foreach ($prices as $priceData) {
            $productId = $priceData['product']['id'];
            $lot = $priceData['lot'];
            $key = "{$productId}_{$lot}";
            $pricesMap[$key] = $priceData['price'];
        }
        
        $groupedByProduct = [];
  
        foreach ($pallets as $palletData) {
      
            // Crear palet
            $pallet = new Pallet();
            $pallet->reception_id = $reception->id;
            $pallet->observations = $palletData['observations'] ?? null;
            
            // Determinar estado según si se indica almacén
            // Si se indica almacén → almacenado, si no → registrado
            $storeId = $palletData['store']['id'] ?? null;
            if ($storeId) {
                $pallet->status = Pallet::STATE_STORED; // Almacenado
            } else {
                $pallet->status = Pallet::STATE_REGISTERED; // Registrado
            }
            
            $pallet->save();
            
            // Crear vínculo con almacén si se proporciona
            if ($storeId) {
                StoredPallet::create([
                    'pallet_id' => $pallet->id,
                    'store_id' => $storeId,
                ]);
            }
      
            // Crear cajas y agrupar por producto+lote
            foreach ($palletData['boxes'] as $boxData) {
                $productId = $boxData['product']['id'];
                // Tomar el lote de la caja, si no tiene generar uno automáticamente
                $boxLot = $boxData['lot'] ?? $this->generateLotFromReception($reception, $productId);
                
                $box = new Box();
                $box->article_id = $productId;
                $box->lot = $boxLot;
                $box->gs1_128 = $boxData['gs1128'];
                $box->gross_weight = $boxData['grossWeight'];
                $box->net_weight = $boxData['netWeight'];
                $box->save();
          
                PalletBox::create([
                    'pallet_id' => $pallet->id,
                    'box_id' => $box->id,
                ]);
                
                // Agrupar por producto y lote (el constraint único es reception_id + product_id + lot)
                $key = "{$productId}_{$boxLot}";
                if (!isset($groupedByProduct[$key])) {
                    // Buscar precio en el mapa de precios, si no existe buscar del histórico
                    $price = $pricesMap[$key] ?? $this->getDefaultPrice($productId, $reception->supplier_id);
                    
                    $groupedByProduct[$key] = [
                        'product_id' => $productId,
                        'lot' => $boxLot,
                        'net_weight' => 0,
                        'price' => $price,
                    ];
                }
                $groupedByProduct[$key]['net_weight'] += $box->net_weight;
            }
        }
  
        // ✅ CORREGIDO: Recalcular totales desde todas las cajas en la BD para asegurar precisión
        // Recargar palets para obtener todas las cajas creadas
        $reception->load('pallets.boxes.box');
        
        // Recalcular totales finales desde todas las cajas
        $finalTotals = [];
        foreach ($reception->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (!$box) {
                    continue;
                }
                
                $key = "{$box->article_id}_{$box->lot}";
                if (!isset($finalTotals[$key])) {
                    $finalTotals[$key] = [
                        'product_id' => $box->article_id,
                        'lot' => $box->lot,
                        'net_weight' => 0,
                    ];
                }
                $finalTotals[$key]['net_weight'] += $box->net_weight;
            }
        }
        
        // Crear líneas de recepción con los totales finales calculados desde la BD
        foreach ($finalTotals as $key => $total) {
            // Obtener precio del mapa de precios o del histórico
            $price = $pricesMap[$key] ?? $this->getDefaultPrice($total['product_id'], $reception->supplier_id);
            
            $reception->products()->create([
                'product_id' => $total['product_id'],
                'lot' => $total['lot'],
                'net_weight' => $total['net_weight'],
                'price' => $price,
            ]);
        }
    }

    /**
     * Actualizar detalles desde request (modo automático - edición)
     * En modo LINES, las cajas se generan automáticamente, así que mantenemos el palet
     * pero recreamos las cajas según los nuevos detalles
     */
    private function updateDetailsFromRequest(RawMaterialReception $reception, array $details, int $supplierId): void
    {
        // Cargar palet único de la recepción
        $reception->load('pallets.boxes.box');
        $pallet = $reception->pallets->first();
        
        if (!$pallet) {
            // Si no existe palet, crear uno nuevo
            $pallet = new Pallet();
            $pallet->reception_id = $reception->id;
            $pallet->observations = "Auto-generado desde recepción #{$reception->id}";
            $pallet->status = Pallet::STATE_REGISTERED;
            $pallet->save();
        } else {
            // Actualizar observaciones del palet existente (mantener el palet)
            $pallet->observations = "Auto-generado desde recepción #{$reception->id}";
            $pallet->save();
            
            // ✅ NUEVO: Verificar si hay cajas en producción antes de eliminar
            $pallet->load('boxes.box.productionInputs');
            foreach ($pallet->boxes as $palletBox) {
                if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                    throw new \Exception("RECEPTION_LINES_MODE: No se puede modificar la recepción porque hay materia prima siendo usada en producción");
                }
            }
            
            // Eliminar cajas existentes (se recrearán según los nuevos detalles)
            foreach ($pallet->boxes as $palletBox) {
                // Usar eliminación directa de BD para evitar el evento deleting
                DB::connection('tenant')->table('boxes')->where('id', $palletBox->box_id)->delete();
            }
            // Eliminar relaciones palet-caja
            DB::connection('tenant')->table('pallet_boxes')->where('pallet_id', $pallet->id)->delete();
        }

        // Eliminar líneas antiguas
        $reception->products()->delete();

        // Crear nuevas líneas y cajas según los detalles
        foreach ($details as $detail) {
            $productId = $detail['product']['id'];
        
            // Obtener precio (del request o del histórico)
            $price = $detail['price'] ?? $this->getDefaultPrice($productId, $supplierId);
        
            $lot = $detail['lot'] ?? $this->generateLotFromReception($reception, $productId);
        
            // Crear línea de recepción
            $reception->products()->create([
                'product_id' => $productId,
                'lot' => $lot,
                'net_weight' => $detail['netWeight'],
                'price' => $price,
            ]);
            $numBoxes = max(1, $detail['boxes'] ?? 1);
            $totalWeight = $detail['netWeight'];
            
            // Calcular peso promedio por caja (redondeado a 2 decimales)
            $weightPerBox = round($totalWeight / $numBoxes, 2);
            
            // Calcular el peso acumulado de las primeras (n-1) cajas
            $accumulatedWeight = $weightPerBox * ($numBoxes - 1);
            
            // La última caja ajusta la diferencia para que la suma sea exacta
            $lastBoxWeight = $totalWeight - $accumulatedWeight;
      
            // Crear nuevas cajas
            for ($i = 0; $i < $numBoxes; $i++) {
                $box = new Box();
                $box->article_id = $productId;
                $box->lot = $lot;
                $box->gs1_128 = $this->generateGS1128($reception, $productId, $i);
                
                // La última caja usa el peso ajustado, las demás el promedio
                $boxNetWeight = ($i === $numBoxes - 1) ? $lastBoxWeight : $weightPerBox;
                $box->gross_weight = round($boxNetWeight * 1.02, 2); // 2% estimado
                $box->net_weight = $boxNetWeight;
                $box->save();
          
                PalletBox::create([
                    'pallet_id' => $pallet->id,
                    'box_id' => $box->id,
                ]);
            }
        }
    }

    /**
     * Crear detalles desde request (modo automático)
     */
    private function createDetailsFromRequest(RawMaterialReception $reception, array $details, int $supplierId): void
    {
        // Crear un solo palet para toda la recepción
        $pallet = new Pallet();
        $pallet->reception_id = $reception->id;
        $pallet->observations = "Auto-generado desde recepción #{$reception->id}";
        $pallet->status = Pallet::STATE_REGISTERED;
        $pallet->save();
  
        foreach ($details as $detail) {
            $productId = $detail['product']['id'];
        
            // Obtener precio (del request o del histórico)
            $price = $detail['price'] ?? $this->getDefaultPrice($productId, $supplierId);
        
            $lot = $detail['lot'] ?? $this->generateLotFromReception($reception, $productId);
        
            // Crear línea de recepción
            $reception->products()->create([
                'product_id' => $productId,
                'lot' => $lot,
                'net_weight' => $detail['netWeight'],
                'price' => $price,
            ]);
      
            $numBoxes = max(1, $detail['boxes'] ?? 1);
            $totalWeight = $detail['netWeight'];
            
            // Calcular peso promedio por caja (redondeado a 2 decimales)
            $weightPerBox = round($totalWeight / $numBoxes, 2);
            
            // Calcular el peso acumulado de las primeras (n-1) cajas
            $accumulatedWeight = $weightPerBox * ($numBoxes - 1);
            
            // La última caja ajusta la diferencia para que la suma sea exacta
            $lastBoxWeight = $totalWeight - $accumulatedWeight;
      
            // Crear cajas
            for ($i = 0; $i < $numBoxes; $i++) {
                $box = new Box();
                $box->article_id = $productId;
                $box->lot = $lot;
                $box->gs1_128 = $this->generateGS1128($reception, $productId, $i);
                
                // La última caja usa el peso ajustado, las demás el promedio
                $boxNetWeight = ($i === $numBoxes - 1) ? $lastBoxWeight : $weightPerBox;
                $box->gross_weight = round($boxNetWeight * 1.02, 2); // 2% estimado
                $box->net_weight = $boxNetWeight;
                $box->save();
          
                PalletBox::create([
                    'pallet_id' => $pallet->id,
                    'box_id' => $box->id,
                ]);
            }
        }
    }

    /**
     * Obtener precio por defecto del histórico
     */
    private function getDefaultPrice(int $productId, int $supplierId): ?float
    {
        // Buscar la última recepción del mismo proveedor con el mismo producto
        $lastReception = RawMaterialReception::where('supplier_id', $supplierId)
            ->whereHas('products', function ($query) use ($productId) {
                $query->where('product_id', $productId)
                      ->whereNotNull('price');
            })
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

    /**
     * Generar lote desde recepción
     * Formato: DDMMAAFFFXXREC
     * DD: Día, MM: Mes, AA: Año (2 dígitos), F: Código FAO, X: ID zona captura, REC: Literal
     */
    private function generateLotFromReception(RawMaterialReception $reception, int $productId): string
    {
        // Cargar producto con relaciones necesarias
        $product = Product::with(['species', 'captureZone'])->find($productId);
        
        if (!$product) {
            // Fallback al formato antiguo si no se encuentra el producto
            return date('Ymd', strtotime($reception->date)) . '-' . $reception->id . '-' . $productId;
        }
        
        // Validar que tiene especie y zona de captura
        if (!$product->species || !$product->capture_zone_id) {
            // Fallback al formato antiguo si faltan datos
            return date('Ymd', strtotime($reception->date)) . '-' . $reception->id . '-' . $productId;
        }
        
        // Obtener fecha de la recepción
        $date = strtotime($reception->date);
        
        // DD: Día (2 dígitos)
        $day = date('d', $date);
        
        // MM: Mes (2 dígitos)
        $month = date('m', $date);
        
        // AA: Año (2 últimos dígitos)
        $year = date('y', $date);
        
        // F: Código FAO (del producto->species->fao)
        $faoCode = $product->species->fao ?? '';
        
        // X: ID de zona de captura (del producto->capture_zone_id) - siempre 2 dígitos con ceros a la izquierda
        $captureZoneId = str_pad((string)$product->capture_zone_id, 2, '0', STR_PAD_LEFT);
        
        // REC: Literal "REC"
        $rec = 'REC';
        
        // Construir lote: DDMMAAFFFXXREC
        return $day . $month . $year . $faoCode . $captureZoneId . $rec;
    }

    /**
     * Generar GS1-128 único
     */
    private function generateGS1128(RawMaterialReception $reception, int $productId, int $index = 0): string
    {
        return 'GS1-' . $reception->id . '-' . $productId . '-' . $index . '-' . time();
    }

    /**
     * Validar que la recepción se puede editar
     * Lanza excepción si no se puede editar
     * 
     * NOTA: Ya no bloquea si hay cajas en producción.
     * La validación de que no se modifiquen cajas usadas se hace en updatePalletsFromRequest()
     */
    private function validateCanEdit(RawMaterialReception $reception): void
    {
        // Cargar relaciones si no están cargadas
        if (!$reception->relationLoaded('pallets')) {
            $reception->load('pallets.reception', 'pallets.boxes.box.productionInputs');
        }

        foreach ($reception->pallets as $pallet) {
            // Validar que el palet no esté vinculado a un pedido
            if ($pallet->order_id !== null) {
                throw new \Exception("No se puede modificar la recepción: el palet #{$pallet->id} está vinculado a un pedido");
            }

            // ✅ NUEVO: Ya no bloqueamos si hay cajas en producción
            // La validación de que no se modifiquen cajas usadas se hará en updatePalletsFromRequest()
        }
    }
}
