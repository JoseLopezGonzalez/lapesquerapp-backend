<?php

namespace App\Http\Controllers\v2;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Store;
use App\Http\Resources\v1\StoreDetailsResource;
use App\Http\Resources\v1\StoreResource;
use App\Http\Resources\v2\StoreDetailsResource as V2StoreDetailsResource;
use App\Http\Resources\v2\StoreResource as V2StoreResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreController extends Controller
{

    private function getDefaultMap(): array
    {
        return [
            "posiciones" => [
                [
                    "id" => 1,
                    "nombre" => "U1",
                    "x" => 40,
                    "y" => 40,
                    "width" => 460,
                    "height" => 238,
                    "tipo" => "center",
                    "nameContainer" => [
                        "x" => 0,
                        "y" => 0,
                        "width" => 230,
                        "height" => 180
                    ],
                ]
            ],
            "elementos" => [
                "fondos" => [
                    [
                        "x" => 0,
                        "y" => 0,
                        "width" => 3410,
                        "height" => 900
                    ]
                ],
                "textos" => []
            ]
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            Log::info('🔵 [STORES INDEX] Inicio del método index', [
                'request_params' => $request->all(),
                'perPage' => $request->input('perPage', 12)
            ]);

            $query = Store::query();
            Log::info('🔵 [STORES INDEX] Query base creado');

            /* filter by id */
            if ($request->has('id')) {
                $query->where('id', $request->id);
                Log::info('🔵 [STORES INDEX] Filtro por id aplicado', ['id' => $request->id]);
            }

            /* filter by ids */
            if ($request->has('ids')) {
                $query->whereIn('id', $request->ids);
                Log::info('🔵 [STORES INDEX] Filtro por ids aplicado', ['ids' => $request->ids]);
            }

            /* filter by name */
            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
                Log::info('🔵 [STORES INDEX] Filtro por name aplicado', ['name' => $request->name]);
            }

            /* ORDER */
            $query->orderBy('name', 'asc');
            Log::info('🔵 [STORES INDEX] Orden aplicado');

            // Cargar la relación palletsV2 para evitar errores en toArrayAssoc()
            $query->with('palletsV2');
            Log::info('🔵 [STORES INDEX] Relación palletsV2 cargada con with()');

            $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
            Log::info('🔵 [STORES INDEX] Antes de paginate', ['perPage' => $perPage]);
            
            $paginated = $query->paginate($perPage);
            Log::info('🔵 [STORES INDEX] Paginate completado', [
                'total' => $paginated->total(),
                'count' => $paginated->count(),
                'current_page' => $paginated->currentPage()
            ]);

            Log::info('🔵 [STORES INDEX] Antes de crear V2StoreResource::collection');
            $resource = V2StoreResource::collection($paginated);
            Log::info('🔵 [STORES INDEX] V2StoreResource::collection creado exitosamente');
            
            return $resource;
        } catch (\Exception $e) {
            Log::error('🔴 [STORES INDEX] Error en index', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'temperature' => 'required|string|max:255',
            'capacity' => 'required|numeric|min:0',
        ]);

        $validated['map'] = json_encode($this->getDefaultMap());

        $store = Store::create($validated);

        return response()->json([
            'message' => 'Almacén creado correctamente',
            'data' => new V2StoreResource($store),
        ], 201);
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $store = Store::with([
            'palletsV2.boxes.box.productionInputs.productionRecord.production', // Cargar productionInputs para determinar disponibilidad
            'palletsV2.boxes.box.product', // Cargar product para toArrayAssocV2
            'palletsV2.storedPallet', // Cargar storedPallet para posición
        ])->findOrFail($id);
        
        return new V2StoreDetailsResource($store);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $store = Store::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'temperature' => 'required|string|max:255',
            'capacity' => 'required|numeric|min:0',
        ]);

        $store->update($validated);

        return response()->json([
            'message' => 'Almacén actualizado correctamente',
            'data' => new V2StoreResource($store),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $store = Store::findOrFail($id);
        $store->delete();

        return response()->json(['message' => 'Almacén eliminado correctamente.']);
    }

    public function deleteMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tenant.stores,id',
        ]);

        Store::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Almacenes eliminados correctamente.']);
    }



    /* Options */
    public function options()
    {
        $store = Store::select('id', 'name')
            ->orderBy('id')
            ->get();

        return response()->json($store);
    }


    public function totalStockByProducts()
    {
        // Obtener palets almacenados (desde StoredPallet)
        $storedPallets = \App\Models\StoredPallet::with([
            'pallet.boxes.box.productionInputs', // Cargar productionInputs para determinar disponibilidad
            'pallet.boxes.box.product',
        ])->get();

        // Obtener palets registrados (status = 1) que no tienen StoredPallet
        $registeredPallets = \App\Models\Pallet::where('status', \App\Models\Pallet::STATE_REGISTERED)
            ->with([
                'boxes.box.productionInputs',
                'boxes.box.product',
            ])
            ->get();

        $products = \App\Models\Product::with('article')->get();

        $productsInventory = [];

        foreach ($products as $product) {
            $totalNetWeight = 0;

            // Procesar palets almacenados
            foreach ($storedPallets as $storedPallet) {
                foreach ($storedPallet->pallet->boxes as $palletBox) {
                    // Solo incluir cajas disponibles (no usadas en producción)
                    if ($palletBox->box->product->id == $product->id && $palletBox->box->isAvailable) {
                        $totalNetWeight += $palletBox->box->net_weight;
                    }
                }
            }

            // Procesar palets registrados
            foreach ($registeredPallets as $pallet) {
                foreach ($pallet->boxes as $palletBox) {
                    // Solo incluir cajas disponibles (no usadas en producción)
                    if ($palletBox->box->product->id == $product->id && $palletBox->box->isAvailable) {
                        $totalNetWeight += $palletBox->box->net_weight;
                    }
                }
            }

            if ($totalNetWeight == 0) {
                continue;
            }

            $productsInventory[] = [
                'id' => $product->id,
                'name' => $product->article->name,
                'total_kg' => round($totalNetWeight, 2),
            ];
        }

        // Calcular total global
        $totalNetWeight = array_sum(array_column($productsInventory, 'total_kg'));

        // Añadir porcentajes
        foreach ($productsInventory as &$productInventory) {
            $productInventory['percentage'] = round(($productInventory['total_kg'] / $totalNetWeight) * 100, 2);
        }

        // Ordenar descendente por total_kg
        usort($productsInventory, function ($a, $b) {
            return $b['total_kg'] <=> $a['total_kg'];
        });

        return response()->json($productsInventory);
    }



}
