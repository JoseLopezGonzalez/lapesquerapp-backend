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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class RawMaterialReceptionController extends Controller
{
    public function index(Request $request)
    {
        $query = RawMaterialReception::query();
        $query->with('supplier', 'products.product', 'pallets');

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
            'pallets.*.product.id' => 'required_with:pallets|exists:tenant.products,id',
            'pallets.*.price' => 'required_with:pallets|numeric|min:0', // Obligatorio en modo manual
            'pallets.*.lot' => 'nullable|string',
            'pallets.*.observations' => 'nullable|string',
            'pallets.*.boxes' => 'required_with:pallets|array',
            'pallets.*.boxes.*.gs1128' => 'required|string',
            'pallets.*.boxes.*.grossWeight' => 'required|numeric',
            'pallets.*.boxes.*.netWeight' => 'required|numeric',
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
            $reception->save();

            // 2. Crear palets y líneas según el modo
            if ($request->has('pallets') && !empty($request->pallets)) {
                // Modo manual: crear palets y generar líneas
                $this->createPalletsFromRequest($reception, $request->pallets);
            } else {
                // Modo automático: crear líneas y generar palet
                $this->createDetailsFromRequest($reception, $request->details, $request->supplier['id']);
            }

            // 3. Cargar relaciones para respuesta
            $reception->load('supplier', 'products.product', 'pallets');
      
            return new RawMaterialReceptionResource($reception);
        });
    }

    public function show($id)
    {
        $reception = RawMaterialReception::with('supplier', 'products.product', 'pallets')->findOrFail($id);
        return new RawMaterialReceptionResource($reception);
    }

    public function update(Request $request, $id)
    {
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

        $reception = RawMaterialReception::findOrFail($id);
  
        return DB::transaction(function () use ($reception, $validated, $request) {
            // Validar que se puede modificar
            $pallets = $reception->pallets;
      
            if ($pallets->count() > 1) {
                throw new \Exception('No se puede modificar una recepción con más de un palet. Use el método de palets directamente.');
            }
      
            if ($pallets->count() === 1) {
                $pallet = $pallets->first();
          
                // Validar que el palet no esté en uso
                if ($pallet->order_id !== null) {
                    throw new \Exception('No se puede modificar la recepción: el palet está vinculado a un pedido');
                }
          
                if ($pallet->status === Pallet::STATE_STORED) {
                    throw new \Exception('No se puede modificar la recepción: el palet está almacenado');
                }
          
                // Validar que las cajas no estén en producción
                foreach ($pallet->boxes as $palletBox) {
                    if ($palletBox->box->productionInputs()->exists()) {
                        throw new \Exception('No se puede modificar la recepción: hay cajas siendo usadas en producción');
                    }
                }
          
                // Eliminar palet y cajas existentes
                foreach ($pallet->boxes as $palletBox) {
                    $palletBox->box->delete();
                }
                $pallet->delete();
            }
      
            // Actualizar recepción
            $reception->update([
                'supplier_id' => $validated['supplier']['id'],
                'date' => $validated['date'],
                'notes' => $validated['notes'] ?? null,
            ]);
      
            // Eliminar líneas antiguas
            $reception->products()->delete();
      
            // Crear nuevas líneas y palets
            $this->createDetailsFromRequest($reception, $validated['details'], $request->supplier['id']);
      
            $reception->load('supplier', 'products.product', 'pallets');
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
     * Crear palets desde request (modo manual)
     */
    private function createPalletsFromRequest(RawMaterialReception $reception, array $pallets): void
    {
        $groupedByProduct = [];
  
        foreach ($pallets as $palletData) {
            $productId = $palletData['product']['id'];
            $lot = $palletData['lot'] ?? $this->generateLotFromReception($reception, $productId);
      
            // Crear palet
            $pallet = new Pallet();
            $pallet->reception_id = $reception->id;
            $pallet->observations = $palletData['observations'] ?? null;
            $pallet->status = Pallet::STATE_REGISTERED;
            $pallet->save();
      
            $totalWeight = 0;
      
            // Crear cajas
            foreach ($palletData['boxes'] as $boxData) {
                $box = new Box();
                $box->article_id = $productId;
                $box->lot = $lot;
                $box->gs1_128 = $boxData['gs1128'];
                $box->gross_weight = $boxData['grossWeight'];
                $box->net_weight = $boxData['netWeight'];
                $box->save();
          
                $totalWeight += $box->net_weight;
          
                PalletBox::create([
                    'pallet_id' => $pallet->id,
                    'box_id' => $box->id,
                ]);
            }
      
            // Agrupar por producto y lote para crear líneas
            $key = "{$productId}_{$lot}";
            if (!isset($groupedByProduct[$key])) {
                $groupedByProduct[$key] = [
                    'product_id' => $productId,
                    'lot' => $lot,
                    'net_weight' => 0,
                    'price' => $palletData['price'],
                ];
            }
            $groupedByProduct[$key]['net_weight'] += $totalWeight;
        }
  
        // Crear líneas de recepción
        foreach ($groupedByProduct as $group) {
            $reception->products()->create([
                'product_id' => $group['product_id'],
                'net_weight' => $group['net_weight'],
                'price' => $group['price'],
            ]);
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
        
            // Crear línea de recepción
            $reception->products()->create([
                'product_id' => $productId,
                'net_weight' => $detail['netWeight'],
                'price' => $price,
            ]);
      
            $lot = $detail['lot'] ?? $this->generateLotFromReception($reception, $productId);
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
     */
    private function generateLotFromReception(RawMaterialReception $reception, int $productId): string
    {
        return date('Ymd', strtotime($reception->date)) . '-' . $reception->id . '-' . $productId;
    }

    /**
     * Generar GS1-128 único
     */
    private function generateGS1128(RawMaterialReception $reception, int $productId, int $index = 0): string
    {
        return 'GS1-' . $reception->id . '-' . $productId . '-' . $index . '-' . time();
    }
}
