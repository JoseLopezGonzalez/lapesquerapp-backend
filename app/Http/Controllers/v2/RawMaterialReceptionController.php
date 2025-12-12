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
      
            return new RawMaterialReceptionResource($reception);
        });
    }

    public function show($id)
    {
        $reception = RawMaterialReception::with('supplier', 'products.product', 'pallets.reception', 'pallets.boxes.box.productionInputs')->findOrFail($id);
        return new RawMaterialReceptionResource($reception);
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
            return new RawMaterialReceptionResource($reception);
        });
    }

    public function destroy($id)
    {

        $order = RawMaterialReception::findOrFail($id);
        $order->delete();
        return response()->json(['message' => 'Palet eliminado correctamente'], 200);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'No se han proporcionado IDs válidos'], 400);
        }

        RawMaterialReception::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Recepciones de materia prima eliminadas con éxito']);
    }

    /**
     * Actualizar palets desde request (modo manual - edición)
     */
    private function updatePalletsFromRequest(RawMaterialReception $reception, array $palletsData, array $prices = []): void
    {
        // Cargar palets existentes con sus cajas
        $reception->load('pallets.boxes.box');
        $existingPallets = $reception->pallets->keyBy('id');
        $processedPalletIds = [];
        $groupedByProduct = [];

        // Crear mapa de precios por producto+lote (compartido por todos los palets)
        $pricesMap = [];
        foreach ($prices as $priceData) {
            $productId = $priceData['product']['id'];
            $lot = $priceData['lot'];
            $key = "{$productId}_{$lot}";
            $pricesMap[$key] = $priceData['price'];
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
                // Recargar cajas del palet
                $pallet->load('boxes.box');
            } else {
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
                    // Actualizar caja existente
                    $box = $existingBoxes->get($boxId)->box;
                    $box->article_id = $productId;
                    $box->lot = $boxLot;
                    $box->gs1_128 = $boxData['gs1128'];
                    $box->gross_weight = $boxData['grossWeight'];
                    $box->net_weight = $boxData['netWeight'];
                    $box->save();
                    $processedBoxIds[] = $boxId;
                } else {
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
                }
                
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

            // Eliminar cajas que ya no están en el request
            $boxesToDelete = $pallet->boxes->filter(function ($palletBox) use ($processedBoxIds) {
                return !in_array($palletBox->box_id, $processedBoxIds);
            });
            
            foreach ($boxesToDelete as $palletBox) {
                // Eliminar caja (ya validamos que no está en producción en validateCanEdit)
                $palletBox->box->delete();
                $palletBox->delete();
            }
            
            // Recargar relación después de eliminar
            if ($boxesToDelete->isNotEmpty()) {
                $pallet->load('boxes.box');
            }
        }

        // Eliminar palets que ya no están en el request
        foreach ($reception->pallets as $pallet) {
            if (!in_array($pallet->id, $processedPalletIds)) {
                // Cargar cajas del palet
                $pallet->load('boxes.box');
                
                // Eliminar relaciones palet-caja y cajas (usando eliminación directa de BD)
                foreach ($pallet->boxes as $palletBox) {
                    DB::connection('tenant')->table('boxes')->where('id', $palletBox->box_id)->delete();
                }
                DB::connection('tenant')->table('pallet_boxes')->where('pallet_id', $pallet->id)->delete();
                // Eliminar palet (usando eliminación directa de BD para evitar el evento)
                DB::connection('tenant')->table('pallets')->where('id', $pallet->id)->delete();
            }
        }

        // Eliminar líneas antiguas y crear nuevas
        $reception->products()->delete();
        foreach ($groupedByProduct as $group) {
            $reception->products()->create([
                'product_id' => $group['product_id'],
                'lot' => $group['lot'],
                'net_weight' => $group['net_weight'],
                'price' => $group['price'],
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
  
        // Crear líneas de recepción agrupadas por producto y lote
        foreach ($groupedByProduct as $group) {
            $reception->products()->create([
                'product_id' => $group['product_id'],
                'lot' => $group['lot'],
                'net_weight' => $group['net_weight'],
                'price' => $group['price'],
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
            $weightPerBox = $detail['netWeight'] / $numBoxes;
      
            // Crear nuevas cajas
            for ($i = 0; $i < $numBoxes; $i++) {
                $box = new Box();
                $box->article_id = $productId;
                $box->lot = $lot;
                $box->gs1_128 = $this->generateGS1128($reception, $productId, $i);
                $box->gross_weight = $weightPerBox * 1.02; // 2% estimado
                $box->net_weight = $weightPerBox;
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
            $weightPerBox = $detail['netWeight'] / $numBoxes;
      
            // Crear cajas
            for ($i = 0; $i < $numBoxes; $i++) {
                $box = new Box();
                $box->article_id = $productId;
                $box->lot = $lot;
                $box->gs1_128 = $this->generateGS1128($reception, $productId, $i);
                $box->gross_weight = $weightPerBox * 1.02; // 2% estimado
                $box->net_weight = $weightPerBox;
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

            // Validar que las cajas no estén en producción
            foreach ($pallet->boxes as $palletBox) {
                if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                    throw new \Exception("No se puede modificar la recepción: la caja #{$palletBox->box->id} está siendo usada en producción");
                }
            }
        }
    }
}
