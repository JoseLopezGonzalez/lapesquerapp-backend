<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\PalletResource;
use App\Models\Box;
use App\Models\Order;
use App\Models\OrderPallet;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\RawMaterialReception;
use App\Models\StoredPallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PalletController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /*return response()->json(['message' => 'Hola Mundo'], 200);*/
    /* return PalletResource::collection(Pallet::all()); */

    /*  public function index()
    {
        return PalletResource::collection(Pallet::paginate(10));

    } */

    /**
     * Carga las relaciones necesarias para el PalletResource
     */
    private function loadPalletRelations($query)
    {
        return $query->with([
            'storedPallet',
            'reception', // Cargar recepción para incluir información en el JSON
            'boxes.box.productionInputs.productionRecord.production', // Cargar productionInputs y su producción para determinar disponibilidad y mostrar info de producción
            'boxes.box.product', // Asegurar que product esté cargado para toArrayAssocV2
        ]);
    }

    private function applyFiltersToQuery($query, $filters)
    {

        if (isset($filters['filters'])) {
            $filters = $filters['filters']; // Para aceptar filtros anidados
        }


        if (isset($filters['id'])) {
            $query->where('id', 'like', "%{$filters['id']}%");
        }

        if (isset($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        if (!empty($filters['state'])) {
            if ($filters['state'] === 'registered') {
                $query->where('status', Pallet::STATE_REGISTERED);
            } elseif ($filters['state'] === 'stored') {
                $query->where('status', Pallet::STATE_STORED);
            } elseif ($filters['state'] === 'shipped') {
                $query->where('status', Pallet::STATE_SHIPPED);
            } elseif ($filters['state'] === 'processed') {
                $query->where('status', Pallet::STATE_PROCESSED);
            }
        }

        if (!empty($filters['orderState'])) {
            if ($filters['orderState'] === 'pending') {
                $query->whereHas('order', fn($q) => $q->where('status', 'pending'));
            } elseif ($filters['orderState'] === 'finished') {
                $query->whereHas('order', fn($q) => $q->where('status', 'finished'));
            } elseif ($filters['orderState'] === 'without_order') {
                $query->whereDoesntHave('order');
            }
        }

        if (!empty($filters['position'])) {
            if ($filters['position'] === 'located') {
                $query->whereHas('storedPallet', fn($q) => $q->whereNotNull('position'));
            } elseif ($filters['position'] === 'unlocated') {
                $query->whereHas('storedPallet', fn($q) => $q->whereNull('position'));
            }
        }

        if (!empty($filters['dates']['start'])) {
            $query->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($filters['dates']['start'])));
        }

        if (!empty($filters['dates']['end'])) {
            $query->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($filters['dates']['end'])));
        }

        if (!empty($filters['notes'])) {
            $query->where('observations', 'like', "%{$filters['notes']}%");
        }

        if (!empty($filters['lots'])) {
            $query->whereHas('boxes.box', fn($q) => $q->whereIn('lot', $filters['lots']));
        }

        if (!empty($filters['products'])) {
            $query->whereHas('boxes.box', fn($q) => $q->whereIn('article_id', $filters['products']));
        }

        if (!empty($filters['species'])) {
            $query->whereHas('boxes.box.product', fn($q) => $q->whereIn('species_id', $filters['species']));
        }

        if (!empty($filters['stores'])) {
            $query->whereHas('storedPallet', fn($q) => $q->whereIn('store_id', $filters['stores']));
        }

        if (!empty($filters['orders'])) {
            $query->whereHas('order', fn($q) => $q->whereIn('order_id', $filters['orders']));
        }

        if (!empty($filters['weights']['netWeight'])) {
            if (isset($filters['weights']['netWeight']['min'])) {
                $min = $filters['weights']['netWeight']['min'];
                $query->whereHas('boxes.box', fn($q) => $q->havingRaw('sum(net_weight) >= ?', [$min]));
            }
            if (isset($filters['weights']['netWeight']['max'])) {
                $max = $filters['weights']['netWeight']['max'];
                $query->whereHas('boxes.box', fn($q) => $q->havingRaw('sum(net_weight) <= ?', [$max]));
            }
        }

        if (!empty($filters['weights']['grossWeight'])) {
            if (isset($filters['weights']['grossWeight']['min'])) {
                $min = $filters['weights']['grossWeight']['min'];
                $query->whereHas('boxes.box', fn($q) => $q->havingRaw('sum(gross_weight) >= ?', [$min]));
            }
            if (isset($filters['weights']['grossWeight']['max'])) {
                $max = $filters['weights']['grossWeight']['max'];
                $query->whereHas('boxes.box', fn($q) => $q->havingRaw('sum(gross_weight) <= ?', [$max]));
            }
        }

        // Filtro para palets con cajas disponibles
        if (!empty($filters['hasAvailableBoxes'])) {
            if ($filters['hasAvailableBoxes'] === true || $filters['hasAvailableBoxes'] === 'true') {
                $query->whereHas('boxes.box', function ($q) {
                    $q->whereDoesntHave('productionInputs');
                });
            }
        }

        // Filtro para palets con cajas usadas
        if (!empty($filters['hasUsedBoxes'])) {
            if ($filters['hasUsedBoxes'] === true || $filters['hasUsedBoxes'] === 'true') {
                $query->whereHas('boxes.box', function ($q) {
                    $q->whereHas('productionInputs');
                });
            }
        }

        // Filtro para solo mostrar cajas disponibles en la respuesta
        // Esto se manejará en el Resource, pero podemos agregar un flag aquí si es necesario

        return $query;
    }


    public function index(Request $request)
    {
        $query = Pallet::query();
        $query = $this->loadPalletRelations($query);

        // Extraemos todos los filtros aplicables del request
        $filters = $request->all();

        // Aplicamos los filtros con el helper reutilizable
        $query = $this->applyFiltersToQuery($query, $filters);

        // Orden y paginación
        $query->orderBy('id', 'desc');
        $perPage = $request->input('perPage', 10);

        return PalletResource::collection($query->paginate($perPage));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        //Validación Sin mensaje JSON
        /* $request->validate([
            'observations' => 'required|string',
            'boxes' => 'required|array',
            'boxes.*.article.id' => 'required|integer',
            'boxes.*.lot' => 'required|string',
            'boxes.*.gs1128' => 'required|string',
            'boxes.*.grossWeight' => 'required|numeric',
            'boxes.*.netWeight' => 'required|numeric',
        ]); */

        //Validación Con mensaje JSON
        $validator = Validator::make($request->all(), [
            'observations' => 'nullable|string',
            'boxes' => 'required|array',
            'boxes.*.product.id' => 'required|integer',
            'boxes.*.lot' => 'required|string',
            'boxes.*.gs1128' => 'required|string',
            'boxes.*.grossWeight' => 'required|numeric',
            'boxes.*.netWeight' => 'required|numeric',
            'store.id' => 'sometimes|nullable|integer|exists:tenant.stores,id',
            'orderId' => 'sometimes|nullable|integer|exists:tenant.orders,id',
            'state.id' => 'sometimes|integer|in:1,2,3,4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Código de estado 422 - Unprocessable Entity
        }


        $pallet = $request->all();
        $boxes = $pallet['boxes'];


        //Insertando Palet
        $newPallet = new Pallet;
        $newPallet->observations = $pallet['observations'];
        
        // Determinar estado según si se indica almacén
        // Si se indica almacén → almacenado, si no → registrado
        $storeId = $pallet['store']['id'] ?? null;
        if ($storeId) {
            $newPallet->status = Pallet::STATE_STORED; // Almacenado
        } else {
            $newPallet->status = $pallet['state']['id'] ?? Pallet::STATE_REGISTERED; // Registrado por defecto
        }
        
        $newPallet->order_id = $pallet['orderId'] ?? null; // Si se proporciona, asignar la orden
        $newPallet->save();

        // Crear vínculo con almacén si se proporciona
        if ($storeId) {
            StoredPallet::create([
                'pallet_id' => $newPallet->id,
                'store_id' => $storeId,
            ]);
        }


        //Insertando Cajas
        foreach ($boxes as $box) {
            $newBox = new Box;
            $newBox->article_id = $box['product']['id'];
            $newBox->lot = $box['lot'];
            $newBox->gs1_128 = $box['gs1128'];
            $newBox->gross_weight = $box['grossWeight'];
            $newBox->net_weight = $box['netWeight'];
            $newBox->save();

            //Agregando Cajas a Palet
            $newPalletBox = new PalletBox;
            $newPalletBox->pallet_id = $newPallet->id;
            $newPalletBox->box_id = $newBox->id;
            $newPalletBox->save();
        }

        /* return resource */
        $newPallet->refresh(); // Refrescar el modelo para obtener los datos actualizados
        $newPallet = $this->loadPalletRelations(Pallet::query()->where('id', $newPallet->id))->first();
        return response()->json(new PalletResource($newPallet), 201); // Código de estado 201 - Created
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pallet = $this->loadPalletRelations(Pallet::query()->where('id', $id))->firstOrFail();
        return response()->json([
            'data' => new PalletResource($pallet),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $pallet = Pallet::with('reception', 'boxes.box.productionInputs')->findOrFail($id);
        
        // Si el palet pertenece a una recepción, validar que se pueda editar
        if ($pallet->reception_id !== null) {
            $reception = $pallet->reception;
            
            // Solo permitir editar palets de recepciones creadas en modo palets
            if ($reception->creation_mode !== RawMaterialReception::CREATION_MODE_PALLETS) {
                return response()->json([
                    'error' => 'No se puede modificar un palet que proviene de una recepción creada por líneas. Modifique desde la recepción.'
                ], 403);
            }
            
            // Validar que el palet no esté vinculado a un pedido
            if ($pallet->order_id !== null) {
                return response()->json([
                    'error' => "No se puede modificar el palet: está vinculado a un pedido"
                ], 403);
            }
            
            // Validar que las cajas no estén en producción
            foreach ($pallet->boxes as $palletBox) {
                if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                    return response()->json([
                        'error' => "No se puede modificar el palet: la caja #{$palletBox->box->id} está siendo usada en producción"
                    ], 403);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'observations' => 'sometimes|nullable|string',
            'store.id' => 'sometimes|nullable|integer',
            'state.id' => 'sometimes|integer',
            'boxes' => 'sometimes|array',
            'boxes.*.id' => 'sometimes|nullable|integer',
            'boxes.*.product.id' => 'required_with:boxes|integer',
            'boxes.*.lot' => 'required_with:boxes|string',
            'boxes.*.gs1128' => 'required_with:boxes|string',
            'boxes.*.grossWeight' => 'required_with:boxes|numeric',
            'boxes.*.netWeight' => 'required_with:boxes|numeric',
            'orderId' => 'sometimes|nullable|integer|exists:tenant.orders,id',
        ]);

        //Cuidado con cambiar validación en la opcion de cambiar a enviado un palet


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Código de estado 422 - Unprocessable Entity
        }

        return DB::transaction(function () use ($request, $pallet, $id) {
            $palletData = $request->all();

            //Creating Pallet
            $updatedPallet = $pallet;

            //Updating Order
            $wasUnlinked = false;
            if ($request->has('orderId')) {
                if ($palletData['orderId'] == null && $updatedPallet->order_id !== null) {
                    $updatedPallet->order_id = null;
                    $wasUnlinked = true;
                } else {
                    // La validación ya verifica que el orderId existe si no es null
                    $updatedPallet->order_id = $palletData['orderId'];
                }
            }

            //Updating State
            $stateWasManuallyChanged = false;
            if ($request->has('state')) {
                if ($updatedPallet->status != $palletData['state']['id']) {
                    // UnStoring pallet if it is in a store
                    if ($updatedPallet->store != null && $palletData['state']['id'] != Pallet::STATE_STORED) {
                        $updatedPallet->unStore();
                    }
                    $updatedPallet->status = $palletData['state']['id'];
                    $stateWasManuallyChanged = true;
                }
            }

            // Si se desvinculó de un pedido y no se cambió el estado manualmente, cambiar automáticamente a registrado
            if ($wasUnlinked && !$stateWasManuallyChanged) {
                if ($updatedPallet->status !== Pallet::STATE_REGISTERED) {
                    $updatedPallet->status = Pallet::STATE_REGISTERED;
                }
                // Quitar almacenamiento si existe
                $updatedPallet->unStore();
            }

            //Updating Observations
            if ($request->has('observations')) {
                if ($palletData['observations'] != $updatedPallet->observations) {
                    $updatedPallet->observations = $palletData['observations'];
                }
            }

            $updatedPallet->save();

            // Updating Store
            if (array_key_exists("store", $palletData)) {
                $storeId = $palletData['store']['id'] ?? null;

                $isPalletStored = StoredPallet::where('pallet_id', $updatedPallet->id)->first();
                if ($isPalletStored) {
                    if ($isPalletStored->store_id != $storeId) {
                        $isPalletStored->delete();
                        if ($storeId) {
                            //Agregando Palet a almacen
                            $newStoredPallet = new StoredPallet;
                            $newStoredPallet->pallet_id = $updatedPallet->id;
                            $newStoredPallet->store_id = $storeId;
                            $newStoredPallet->save();
                        }
                    }
                } else {
                    if ($storeId) {
                        //Agregando Palet a almacen
                        $newStoredPallet = new StoredPallet;
                        $newStoredPallet->pallet_id = $updatedPallet->id;
                        $newStoredPallet->store_id = $storeId;
                        $newStoredPallet->save();
                    }
                }
            }

            //Updating Boxes
            if (array_key_exists("boxes", $palletData)) {
                $boxes = $palletData['boxes'];

                //Eliminando Cajas y actualizando
                $updatedPallet->boxes->map(function ($box) use (&$boxes) {
                    $hasBeenUpdated = false;

                    foreach ($boxes as $index => $updatedBox) {
                        if ($updatedBox['id'] == $box->box->id) {
                            $box->box->article_id = $updatedBox['product']['id'];
                            $box->box->lot = $updatedBox['lot'];
                            $box->box->gs1_128 = $updatedBox['gs1128'];
                            $box->box->gross_weight = $updatedBox['grossWeight'];
                            $box->box->net_weight = $updatedBox['netWeight'];
                            $box->box->save();
                            $hasBeenUpdated = true;
                            //Eliminando Caja del array para añadir
                            unset($boxes[$index]);
                        }
                    }

                    if (!$hasBeenUpdated) {
                        $box->box->delete();
                    }
                });

                $boxes = array_values($boxes);

                //Insertando Cajas
                foreach ($boxes as $box) {
                    $newBox = new Box;
                    $newBox->article_id = $box['product']['id'];
                    $newBox->lot = $box['lot'];
                    $newBox->gs1_128 = $box['gs1128'];
                    $newBox->gross_weight = $box['grossWeight'];
                    $newBox->net_weight = $box['netWeight'];
                    $newBox->save();

                    //Agregando Cajas a Palet
                    $newPalletBox = new PalletBox;
                    $newPalletBox->pallet_id = $updatedPallet->id;
                    $newPalletBox->box_id = $newBox->id;
                    $newPalletBox->save();
                }
            }

            $updatedPallet->refresh();

            // Si el palet pertenece a una recepción creada en modo palets, actualizar las líneas de recepción
            if ($updatedPallet->reception_id !== null) {
                $reception = $updatedPallet->reception;
                if ($reception && $reception->creation_mode === RawMaterialReception::CREATION_MODE_PALLETS) {
                    $this->updateReceptionLinesFromPallets($reception);
                }
            }

            // Cargar relaciones y devolver el palet actualizado
            $updatedPallet = $this->loadPalletRelations(Pallet::query()->where('id', $id))->firstOrFail();
            return response()->json(new PalletResource($updatedPallet), 201);
        });

        //return response()->json($updatedPallet->toArrayAssoc(), 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pallet = Pallet::findOrFail($id);
        
        // Validar que no se pueda eliminar un palet de recepción
        if ($pallet->reception_id !== null) {
            return response()->json([
                'error' => 'No se puede eliminar un palet que proviene de una recepción. Elimine la recepción o modifique desde la recepción.'
            ], 403);
        }

        DB::transaction(function () use ($pallet) {
            // Eliminar registros relacionados primero
            if ($pallet->storedPallet) {
                $pallet->storedPallet->delete();
            }
            
            // Eliminar las cajas asociadas al palet
            $pallet->boxes()->delete();
            
            // Eliminar el palet
            $pallet->delete();
        });

        return response()->json(['message' => 'Palet eliminado correctamente']);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tenant.pallets,id',
        ]);

        $palletIds = $validated['ids'];
        
        // Validar que ninguno de los palets pertenezca a una recepción
        $palletsWithReception = Pallet::whereIn('id', $palletIds)
            ->whereNotNull('reception_id')
            ->get();
        
        if ($palletsWithReception->isNotEmpty()) {
            $palletIdsList = $palletsWithReception->pluck('id')->implode(', ');
            return response()->json([
                'error' => "No se pueden eliminar palets que provienen de una recepción. Los siguientes palets pertenecen a una recepción: {$palletIdsList}. Elimine la recepción o modifique desde la recepción."
            ], 403);
        }

        DB::transaction(function () use ($palletIds) {
            // Eliminar registros relacionados primero
            StoredPallet::whereIn('pallet_id', $palletIds)->delete();
            
            // Eliminar las cajas asociadas a los palets
            PalletBox::whereIn('pallet_id', $palletIds)->delete();
            
            // Eliminar los palets
            Pallet::whereIn('id', $palletIds)->delete();
        });

        return response()->json(['message' => 'Palets eliminados correctamente']);
    }


    public function assignToPosition(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'position_id' => 'required|integer|min:1',
            'pallet_ids' => 'required|array|min:1',
            'pallet_ids.*' => 'integer|exists:tenant.pallets,id',
        ]);

        if ($validated->fails()) {
            return response()->json(['errors' => $validated->errors()], 422);
        }

        $positionId = $request->input('position_id');
        $palletIds = $request->input('pallet_ids');

        foreach ($palletIds as $palletId) {
            $stored = StoredPallet::firstOrNew(['pallet_id' => $palletId]);
            $stored->position = $positionId;
            $stored->save();
        }

        return response()->json(['message' => 'Palets ubicados correctamente'], 200);
    }

    public function moveToStore(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'pallet_id' => 'required|integer|exists:tenant.pallets,id',
            'store_id' => 'required|integer|exists:tenant.stores,id',
        ]);

        if ($validated->fails()) {
            return response()->json(['errors' => $validated->errors()], 422);
        }

        $palletId = $request->input('pallet_id');
        $storeId = $request->input('store_id');

        $pallet = Pallet::findOrFail($palletId);

        // Permitir mover desde almacén ghost (estado registrado) o desde almacén almacenado
        // Si está en estado registrado, cambiar automáticamente a almacenado
        if ($pallet->status === Pallet::STATE_REGISTERED) {
            // Cambiar automáticamente el estado a almacenado cuando se mueve desde el almacén ghost
            $pallet->status = Pallet::STATE_STORED;
            $pallet->save();
        } elseif ($pallet->status !== Pallet::STATE_STORED) {
            // No permitir mover palets en otros estados (shipped, processed)
            return response()->json(['error' => 'El palet no está en estado almacenado o registrado'], 400);
        }

        $storedPallet = StoredPallet::firstOrNew(['pallet_id' => $palletId]);
        $storedPallet->store_id = $storeId;
        $storedPallet->position = null; // ← resetea la posición al mover de almacén
        $storedPallet->save();

        $pallet->refresh();
        $pallet = $this->loadPalletRelations(Pallet::query()->where('id', $pallet->id))->first();
        return response()->json([
            'message' => 'Palet movido correctamente al nuevo almacén',
            'pallet' => new PalletResource($pallet),
        ], 200);
    }

    public function moveMultipleToStore(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'pallet_ids' => 'required|array|min:1',
            'pallet_ids.*' => 'integer|exists:tenant.pallets,id',
            'store_id' => 'required|integer|exists:tenant.stores,id',
        ]);

        if ($validated->fails()) {
            return response()->json(['errors' => $validated->errors()], 422);
        }

        $palletIds = $request->input('pallet_ids');
        $storeId = $request->input('store_id');

        $movedCount = 0;
        $errors = [];

        foreach ($palletIds as $palletId) {
            try {
                $pallet = Pallet::findOrFail($palletId);

                // Permitir mover desde almacén ghost (estado registrado) o desde almacén almacenado
                // Si está en estado registrado, cambiar automáticamente a almacenado
                if ($pallet->status === Pallet::STATE_REGISTERED) {
                    // Cambiar automáticamente el estado a almacenado cuando se mueve desde el almacén ghost
                    $pallet->status = Pallet::STATE_STORED;
                    $pallet->save();
                } elseif ($pallet->status !== Pallet::STATE_STORED) {
                    // No permitir mover palets en otros estados (shipped, processed)
                    $errors[] = [
                        'pallet_id' => $palletId,
                        'error' => 'El palet no está en estado almacenado o registrado'
                    ];
                    continue;
                }

                $storedPallet = StoredPallet::firstOrNew(['pallet_id' => $palletId]);
                $storedPallet->store_id = $storeId;
                $storedPallet->position = null; // ← resetea la posición al mover de almacén
                $storedPallet->save();

                $movedCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'pallet_id' => $palletId,
                    'error' => $e->getMessage()
                ];
            }
        }

        $response = [
            'message' => "Se movieron {$movedCount} palet(s) correctamente al nuevo almacén",
            'moved_count' => $movedCount,
            'total_count' => count($palletIds),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, 200);
    }

    public function unassignPosition($id)
    {
        $stored = StoredPallet::where('pallet_id', $id)->first();

        if (!$stored) {
            return response()->json(['error' => 'El palet no está almacenado'], 404);
        }

        $stored->position = null;
        $stored->save();

        return response()->json([
            'message' => 'Posición eliminada correctamente del palet',
            'pallet_id' => $id,
        ], 200);
    }


    public function bulkUpdateState(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'status' => 'required|integer|in:1,2,3,4',
            'ids' => 'array|required_without_all:filters,applyToAll',
            'ids.*' => 'integer|exists:tenant.pallets,id',
            'filters' => 'array|required_without_all:ids,applyToAll',
            'applyToAll' => 'boolean|required_without_all:ids,filters',
        ]);

        if ($validated->fails()) {
            return response()->json(['errors' => $validated->errors()], 422);
        }

        $stateId = $request->input('status');
        $palletsQuery = Pallet::with('storedPallet');

        if ($request->filled('ids')) {
            $palletsQuery->whereIn('id', $request->input('ids'));
        } elseif ($request->filled('filters')) {
            $palletsQuery = $this->applyFiltersToQuery($palletsQuery, ['filters' => $request->input('filters')]);

        } elseif (!$request->boolean('applyToAll')) {
            return response()->json(['error' => 'No se especificó ninguna condición válida para seleccionar pallets.'], 400);
        }

        $pallets = $palletsQuery->get();
        $updatedCount = 0;

        foreach ($pallets as $pallet) {
            if ($pallet->status != $stateId) {
                if ($stateId !== Pallet::STATE_STORED && $pallet->storedPallet) {
                    $pallet->unStore();
                }

                if ($stateId === Pallet::STATE_STORED && !$pallet->storedPallet) {
                    StoredPallet::create([
                        'pallet_id' => $pallet->id,
                        'store_id' => 4, // puedes hacer dinámico
                    ]);
                }

                $pallet->status = $stateId;
                $pallet->save();
                $updatedCount++;
            }
        }

        return response()->json([
            'message' => 'Palets actualizados correctamente',
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Link a pallet to an order
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function linkOrder(Request $request, string $id)
    {
        $validated = $request->validate([
            'orderId' => 'required|integer|exists:tenant.orders,id',
        ]);

        $pallet = Pallet::findOrFail($id);

        // Check if pallet is already linked to this order
        if ($pallet->order_id == $validated['orderId']) {
            $pallet = $this->loadPalletRelations(Pallet::query()->where('id', $id))->first();
            return response()->json([
                'message' => 'El palet ya está vinculado a este pedido',
                'pallet' => new PalletResource($pallet)
            ], 200);
        }

        // Check if pallet is already linked to a different order
        if ($pallet->order_id !== null) {
            return response()->json([
                'error' => "El palet #{$id} ya está vinculado al pedido #{$pallet->order_id}. Debe desvincularlo primero."
            ], 400);
        }

        // Link the pallet to the order
        $pallet->order_id = $validated['orderId'];
        $pallet->save();

        $pallet = $this->loadPalletRelations(Pallet::query()->where('id', $id))->first();
        return response()->json([
            'message' => 'Palet vinculado correctamente al pedido',
            'pallet_id' => $id,
            'order_id' => $validated['orderId'],
            'pallet' => new PalletResource($pallet)
        ], 200);
    }

    /**
     * Link multiple pallets to orders
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function linkOrders(Request $request)
    {
        $validated = $request->validate([
            'pallets' => 'required|array|min:1',
            'pallets.*.id' => 'required|integer|exists:tenant.pallets,id',
            'pallets.*.orderId' => 'required|integer|exists:tenant.orders,id',
        ]);

        $results = [];
        $errors = [];

        foreach ($validated['pallets'] as $palletData) {
            $palletId = $palletData['id'];
            $orderId = $palletData['orderId'];

            try {
                $pallet = Pallet::findOrFail($palletId);

                // Check if pallet is already linked to this order
                if ($pallet->order_id == $orderId) {
                    $results[] = [
                        'pallet_id' => $palletId,
                        'order_id' => $orderId,
                        'status' => 'already_linked',
                        'message' => 'El palet ya estaba vinculado a este pedido'
                    ];
                    continue;
                }

                // Check if palet is already linked to a different order
                if ($pallet->order_id !== null) {
                    $errors[] = [
                        'pallet_id' => $palletId,
                        'order_id' => $orderId,
                        'error' => "El palet #{$palletId} ya está vinculado al pedido #{$pallet->order_id}"
                    ];
                    continue;
                }

                // Link the pallet to the order
                $pallet->order_id = $orderId;
                $pallet->save();

                $results[] = [
                    'pallet_id' => $palletId,
                    'order_id' => $orderId,
                    'status' => 'linked',
                    'message' => 'Palet vinculado correctamente'
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'pallet_id' => $palletId,
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ];
            }
        }

        $response = [
            'message' => 'Proceso de vinculación completado',
            'linked' => count(array_filter($results, fn($r) => $r['status'] === 'linked')),
            'already_linked' => count(array_filter($results, fn($r) => $r['status'] === 'already_linked')),
            'errors' => count($errors),
            'results' => $results,
        ];

        if (!empty($errors)) {
            $response['errors_details'] = $errors;
        }

        $statusCode = empty($errors) ? 200 : 207; // 207 Multi-Status si hay algunos errores
        return response()->json($response, $statusCode);
    }

    /**
     * Unlink a pallet from its associated order
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlinkOrder(string $id)
    {
        $pallet = Pallet::findOrFail($id);

        // Check if pallet is already unlinked from any order
        if (!$pallet->order_id) {
            $pallet = $this->loadPalletRelations(Pallet::query()->where('id', $id))->first();
            return response()->json([
                'message' => 'El palet ya no está asociado a ninguna orden',
                'pallet' => new PalletResource($pallet)
            ], 200);
        }

        // Store the order ID before unlinking for the response
        $orderId = $pallet->order_id;

        // Unlink the pallet from the order and change to registered state in the same operation
        $pallet->order_id = null;
        // Cambiar automáticamente a estado registrado cuando se desvincula de un pedido
        if ($pallet->status !== Pallet::STATE_REGISTERED) {
            $pallet->status = Pallet::STATE_REGISTERED;
        }
        // Quitar almacenamiento si existe
        $pallet->unStore();
        $pallet->save();

        $pallet = $this->loadPalletRelations(Pallet::query()->where('id', $id))->first();
        return response()->json([
            'message' => 'Palet desvinculado correctamente de la orden',
            'pallet_id' => $id,
            'order_id' => $orderId,
            'pallet' => new PalletResource($pallet)
        ], 200);
    }

    /**
     * Unlink multiple pallets from their associated orders
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlinkOrders(Request $request)
    {
        $validated = $request->validate([
            'pallet_ids' => 'required|array|min:1',
            'pallet_ids.*' => 'required|integer|exists:tenant.pallets,id',
        ]);

        $results = [];
        $errors = [];

        foreach ($validated['pallet_ids'] as $palletId) {
            try {
                $pallet = Pallet::findOrFail($palletId);

                // Check if pallet is already unlinked from any order
                if (!$pallet->order_id) {
                    $results[] = [
                        'pallet_id' => $palletId,
                        'status' => 'already_unlinked',
                        'message' => 'El palet ya no está asociado a ninguna orden'
                    ];
                    continue;
                }

                // Store the order ID before unlinking for the response
                $orderId = $pallet->order_id;

                // Unlink the pallet from the order and change to registered state in the same operation
                $pallet->order_id = null;
                // Cambiar automáticamente a estado registrado cuando se desvincula de un pedido
                if ($pallet->status !== Pallet::STATE_REGISTERED) {
                    $pallet->status = Pallet::STATE_REGISTERED;
                }
                // Quitar almacenamiento si existe
                $pallet->unStore();
                $pallet->save();

                $results[] = [
                    'pallet_id' => $palletId,
                    'order_id' => $orderId,
                    'status' => 'unlinked',
                    'message' => 'Palet desvinculado correctamente'
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'pallet_id' => $palletId,
                    'error' => $e->getMessage()
                ];
            }
        }

        $response = [
            'message' => 'Proceso de desvinculación completado',
            'unlinked' => count(array_filter($results, fn($r) => $r['status'] === 'unlinked')),
            'already_unlinked' => count(array_filter($results, fn($r) => $r['status'] === 'already_unlinked')),
            'errors' => count($errors),
            'results' => $results,
        ];

        if (!empty($errors)) {
            $response['errors_details'] = $errors;
        }

        $statusCode = empty($errors) ? 200 : 207; // 207 Multi-Status si hay algunos errores
        return response()->json($response, $statusCode);
    }

    /**
     * Obtener palets registrados como si fuera un almacén
     * Retorna un formato similar a StoreDetailsResource para mantener consistencia
     */
    /**
     * Actualizar líneas de recepción basándose en los palets actualizados
     * Se llama cuando se edita un palet de una recepción creada en modo palets
     */
    private function updateReceptionLinesFromPallets(RawMaterialReception $reception): void
    {
        // Cargar todos los palets de la recepción con sus cajas y líneas existentes
        $reception->load('pallets.boxes.box', 'products');
        
        // Obtener precios existentes por producto+lote (usar el precio de la línea con más peso si hay múltiples)
        $existingPrices = [];
        foreach ($reception->products as $product) {
            $productId = $product->product_id;
            $lot = $product->lot;
            $key = "{$productId}_{$lot}";
            if (!isset($existingPrices[$key]) || 
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
                if (!$box) {
                    continue;
                }
                
                $productId = $box->article_id;
                $lot = $box->lot;
                $netWeight = $box->net_weight ?? 0;
                
                // Obtener el precio de las líneas existentes del mismo producto+lote
                $key = "{$productId}_{$lot}";
                $price = $existingPrices[$key]['price'] ?? null;
                
                // Agrupar por producto y lote
                $key = "{$productId}_{$lot}";
                if (!isset($groupedByProduct[$key])) {
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
        
        // Eliminar líneas antiguas
        $reception->products()->delete();
        
        // Crear nuevas líneas de recepción
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
     * Buscar palets registrados por lote
     * Retorna solo palets que tienen cajas disponibles con el lote especificado
     */
    public function searchByLot(Request $request)
    {
        $lot = $request->query('lot');

        if (!$lot) {
            return response()->json([
                'message' => 'El parámetro lot es requerido.',
                'userMessage' => 'Debe proporcionar el número de lote para buscar.'
            ], 400);
        }

        // Buscar palets registrados que tengan cajas con el lote especificado y que estén disponibles
        $pallets = Pallet::where('status', Pallet::STATE_REGISTERED)
            ->whereHas('boxes.box', function ($query) use ($lot) {
                $query->where('lot', $lot)
                      ->whereDoesntHave('productionInputs'); // Solo cajas disponibles
            })
            ->with([
                'boxes.box' => function ($query) use ($lot) {
                    // Cargar todas las cajas del palet, luego filtraremos
                    $query->with(['product', 'productionInputs.productionRecord.production']);
                },
                'storedPallet',
                'reception'
            ])
            ->orderBy('id', 'desc')
            ->get();

        // Filtrar las cajas y formatear los palets
        $formattedPallets = $pallets->map(function ($pallet) use ($lot) {
            // Filtrar cajas: solo las del lote especificado y disponibles
            $filteredBoxes = $pallet->boxes->filter(function ($palletBox) use ($lot) {
                $box = $palletBox->box;
                if (!$box) {
                    return false;
                }
                // Verificar que el lote coincida (case-insensitive) y que esté disponible
                return strtolower($box->lot ?? '') === strtolower($lot) && $box->isAvailable;
            });

            // Si no hay cajas después del filtrado, excluir este palet
            if ($filteredBoxes->isEmpty()) {
                return null;
            }

            // Obtener el array base del palet
            $palletArray = $pallet->toArrayAssocV2();
            
            // Reemplazar las cajas con las filtradas
            $palletArray['boxes'] = $filteredBoxes->map(function ($palletBox) {
                return $palletBox->box ? $palletBox->box->toArrayAssocV2() : null;
            })->filter()->values();

            return $palletArray;
        })->filter()->values(); // Filtrar nulls

        // Calcular totales
        $total = $formattedPallets->count();
        $totalBoxes = $formattedPallets->sum(function ($pallet) {
            return count($pallet['boxes'] ?? []);
        });

        return response()->json([
            'data' => [
                'pallets' => $formattedPallets,
                'total' => $total,
                'totalBoxes' => $totalBoxes,
            ],
        ], 200);
    }

    public function registeredPallets()
    {
        // Obtener todos los palets registrados (status = 1) con relaciones cargadas
        $query = Pallet::where('status', Pallet::STATE_REGISTERED);
        $query = $this->loadPalletRelations($query);
        $pallets = $query->orderBy('id', 'desc')->get();

        // Calcular pesos totales - usar sum con callback para acceder al accessor
        $netWeightPallets = $pallets->sum(function ($pallet) {
            return $pallet->netWeight ?? 0;
        });
        $totalNetWeight = $netWeightPallets; // No hay boxes ni bigBoxes por ahora

        // Formato similar a StoreDetailsResource
        return response()->json([
            'id' => null, // No es un almacén real
            'name' => 'Palets Registrados',
            'temperature' => null,
            'capacity' => null,
            'netWeightPallets' => round($netWeightPallets, 3),
            'totalNetWeight' => round($totalNetWeight, 3),
            'content' => [
                'pallets' => $pallets->map(function ($pallet) {
                    return $pallet->toArrayAssocV2();
                })->values(), // values() para resetear índices
                'boxes' => [],
                'bigBoxes' => [],
            ],
            'map' => null, // No hay mapa para palets registrados
        ], 200);
    }

    /**
     * Listar palets disponibles para vincular a un pedido
     * 
     * Solo palets almacenados o registrados, excluyendo los que están vinculados a otros pedidos
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function availableForOrder(Request $request)
    {
        $validated = $request->validate([
            'orderId' => 'nullable|integer|exists:tenant.orders,id',
            'id' => 'nullable|string', // Filtro por ID con coincidencias
            'storeId' => 'nullable|integer|exists:tenant.stores,id',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $orderId = $validated['orderId'] ?? null;
        $idFilter = $validated['id'] ?? null;
        $storeId = $validated['storeId'] ?? null;
        $perPage = $validated['perPage'] ?? 20;

        // Construir query base
        $query = Pallet::query()
            ->whereIn('status', [Pallet::STATE_REGISTERED, Pallet::STATE_STORED])
            ->with([
                'boxes.box.product',
                'boxes.box.productionInputs', // Para calcular disponibilidad
                'storedPallet.store',
                'reception'
            ]);

        // Filtrar por ID si se proporciona (búsqueda por coincidencias)
        if ($idFilter) {
            $query->where('id', 'like', "%{$idFilter}%");
        }

        // Filtrar por almacén si se proporciona storeId
        if ($storeId !== null) {
            // Solo incluir palets que tienen storedPallet con el store_id especificado
            $query->whereHas('storedPallet', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        // Excluir palets vinculados a otros pedidos
        // Si se proporciona orderId, incluir palets sin pedido O del mismo pedido (para permitir cambio)
        if ($orderId) {
            $query->where(function ($q) use ($orderId) {
                $q->whereNull('order_id')
                  ->orWhere('order_id', $orderId);
            });
        } else {
            // Si no se proporciona orderId, solo palets sin pedido
            $query->whereNull('order_id');
        }

        // Ordenar por ID descendente
        $query->orderBy('id', 'desc');

        // Paginación
        $pallets = $query->paginate($perPage);

        // Formatear respuesta con datos relevantes
        $formattedPallets = $pallets->map(function ($pallet) {
            // Calcular resumen por producto
            $productsSummary = [];
            
            if ($pallet->boxes && $pallet->boxes->isNotEmpty()) {
                // Agrupar cajas por producto
                $boxesByProduct = $pallet->boxes->groupBy(function ($palletBox) {
                    return $palletBox->box && $palletBox->box->product 
                        ? $palletBox->box->product->id 
                        : null;
                })->filter(function ($boxes, $productId) {
                    return $productId !== null;
                });
                
                foreach ($boxesByProduct as $productId => $productBoxes) {
                    $firstBox = $productBoxes->first()->box;
                    $product = $firstBox->product;
                    
                    if (!$product) {
                        continue;
                    }
                    
                    // Calcular cajas disponibles y usadas
                    $availableBoxes = $productBoxes->filter(function ($palletBox) {
                        return $palletBox->box && $palletBox->box->isAvailable;
                    });
                    
                    $availableBoxCount = $availableBoxes->count();
                    $availableNetWeight = $availableBoxes->sum(function ($palletBox) {
                        return $palletBox->box->net_weight ?? 0;
                    });
                    
                    $totalBoxCount = $productBoxes->count();
                    $totalNetWeight = $productBoxes->sum(function ($palletBox) {
                        return $palletBox->box->net_weight ?? 0;
                    });
                    
                    $productsSummary[] = [
                        'product' => [
                            'id' => $product->id,
                            'name' => $product->name,
                        ],
                        'availableBoxCount' => $availableBoxCount,
                        'availableNetWeight' => $availableNetWeight !== null ? round($availableNetWeight, 3) : 0,
                        'totalBoxCount' => $totalBoxCount,
                        'totalNetWeight' => $totalNetWeight !== null ? round($totalNetWeight, 3) : 0,
                    ];
                }
            }
            
            return [
                'id' => $pallet->id,
                'status' => $pallet->status,
                'state' => [
                    'id' => $pallet->status,
                    'name' => $pallet->stateArray['name'] ?? null,
                ],
                'productsNames' => $pallet->productsNames ?? [],
                'lots' => $pallet->lots ?? [],
                'productsSummary' => $productsSummary,
                'numberOfBoxes' => $pallet->numberOfBoxes ?? 0,
                'availableBoxesCount' => $pallet->availableBoxesCount ?? 0,
                'netWeight' => $pallet->netWeight !== null ? round($pallet->netWeight, 3) : null,
                'totalAvailableWeight' => $pallet->totalAvailableWeight !== null ? round($pallet->totalAvailableWeight, 3) : null,
                'storedPallet' => $pallet->storedPallet ? [
                    'store_id' => $pallet->storedPallet->store_id,
                    'position' => $pallet->storedPallet->position,
                ] : null,
                'order_id' => $pallet->order_id,
                'receptionId' => $pallet->reception_id,
                'observations' => $pallet->observations,
            ];
        });

        return response()->json([
            'data' => $formattedPallets,
            'current_page' => $pallets->currentPage(),
            'last_page' => $pallets->lastPage(),
            'per_page' => $pallets->perPage(),
            'total' => $pallets->total(),
        ], 200);
    }

}
