<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DeleteMultipleStoresRequest;
use App\Http\Requests\v2\IndexStoreRequest;
use App\Http\Requests\v2\StoreStoreRequest;
use App\Http\Requests\v2\UpdateStoreRequest;
use App\Http\Resources\v2\StoreDetailsResource;
use App\Http\Resources\v2\StoreResource;
use App\Models\Store;
use App\Services\ActorScopeService;

class StoreController extends Controller
{
    public function __construct(
        protected ActorScopeService $scope
    ) {}

    private function getDefaultMap(): array
    {
        return [
            'posiciones' => [
                [
                    'id' => 1,
                    'nombre' => 'U1',
                    'x' => 40,
                    'y' => 40,
                    'width' => 460,
                    'height' => 238,
                    'tipo' => 'center',
                    'nameContainer' => [
                        'x' => 0,
                        'y' => 0,
                        'width' => 230,
                        'height' => 180,
                    ],
                ],
            ],
            'elementos' => [
                'fondos' => [
                    [
                        'x' => 0,
                        'y' => 0,
                        'width' => 3410,
                        'height' => 900,
                    ],
                ],
                'textos' => [],
            ],
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(IndexStoreRequest $request)
    {
        $query = Store::query();
        $query = $this->scope->scopeStores($query, $request->user());

        /* filter by id */
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        /* filter by ids */
        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        /* filter by name */
        if ($request->has('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }

        if ($request->filled('store_type')) {
            $query->where('store_type', $request->input('store_type'));
        }

        if ($request->filled('external_user_id')) {
            $query->where('external_user_id', $request->integer('external_user_id'));
        }

        /* ORDER */
        $query->orderBy('name', 'asc');

        // Cargar relaciones necesarias para serialización sin lazy loading.
        $query->with([
            'palletsV2.boxes.box.productionInputs',
            'palletsV2.boxes.box.product',
            'externalUser',
        ]);

        $perPage = $request->input('perPage', 12); //  Default a 10 si no se proporciona

        return StoreResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoreRequest $request)
    {
        $validated = $request->validated();
        $validated['map'] = json_encode($this->getDefaultMap());
        $validated['store_type'] = $validated['store_type'] ?? 'interno';

        $store = Store::create($validated);

        return response()->json([
            'message' => 'Almacén creado correctamente',
            'data' => new StoreResource($store),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Aumentar límites de memoria y tiempo para almacenes con muchos pallets
        $limits = config('exports.operations.store');
        if ($limits) {
            ini_set('memory_limit', $limits['memory_limit']);
            set_time_limit($limits['max_execution_time']);
        }

        $store = Store::with([
            'palletsV2.boxes.box.productionInputs.productionRecord.production', // Cargar productionInputs para determinar disponibilidad
            'palletsV2.boxes.box.product', // Cargar product para toArrayAssocV2
            'palletsV2.storedPallet', // Cargar storedPallet para posición
            'externalUser',
        ])->findOrFail($id);
        $this->authorize('view', $store);

        return response()->json([
            'data' => new StoreDetailsResource($store),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStoreRequest $request, string $id)
    {
        $store = Store::findOrFail($id);
        $this->authorize('update', $store);
        $validated = $request->validated();

        $store->update($validated);

        return response()->json([
            'message' => 'Almacén actualizado correctamente',
            'data' => new StoreResource($store),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $store = Store::findOrFail($id);
        $this->authorize('delete', $store);
        $store->delete();

        return response()->json(['message' => 'Almacén eliminado correctamente.']);
    }

    public function deleteMultiple(DeleteMultipleStoresRequest $request)
    {
        $validated = $request->validated();
        Store::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Almacenes eliminados correctamente.']);
    }

    /* Options */
    public function options()
    {
        $this->authorize('viewOptions', Store::class);
        $stores = Store::select('id', 'name')
            ->when($this->scope->isExternal(request()->user()), fn ($query) => $query->whereIn('id', $this->scope->allowedStoreIds(request()->user())))
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($stores);
    }

    public function totalStockByProducts()
    {
        $this->authorize('viewAny', Store::class);
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

        $products = \App\Models\Product::all();

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
                'name' => $product->name,
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
