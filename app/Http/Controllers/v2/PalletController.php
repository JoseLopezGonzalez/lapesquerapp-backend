<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\PalletResource;
use App\Models\Box;
use App\Models\Order;
use App\Models\OrderPallet;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\StoredPallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            if ($filters['state'] === 'stored') {
                $query->where('state_id', 2);
            } elseif ($filters['state'] === 'shipped') {
                $query->where('state_id', 3);
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

        return $query;
    }


    public function index(Request $request)
    {
        $query = Pallet::query()->with('storedPallet');

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
            'state.id' => 'sometimes|integer|exists:tenant.pallet_states,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Código de estado 422 - Unprocessable Entity
        }


        $pallet = $request->all();
        $boxes = $pallet['boxes'];


        //Insertando Palet
        $newPallet = new Pallet;
        $newPallet->observations = $pallet['observations'];
        $newPallet->state_id = $pallet['state']['id'] ?? 1; // Por defecto, estado registrado
        $newPallet->order_id = $pallet['orderId'] ?? null; // Si se proporciona, asignar la orden
        $newPallet->save();

        // Crear vínculo con almacén si se proporciona
        if (isset($pallet['store']['id'])) {
            StoredPallet::create([
                'pallet_id' => $newPallet->id,
                'store_id' => $pallet['store']['id'],
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
        return response()->json(new PalletResource($newPallet), 201); // Código de estado 201 - Created
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return new PalletResource(Pallet::findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
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
            'orderId' => 'sometimes|nullable|integer',
        ]);

        //Cuidado con cambiar validación en la opcion de cambiar a enviado un palet


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Código de estado 422 - Unprocessable Entity
        }



        $pallet = $request->all();

        //Creating Pallet
        $updatedPallet = Pallet::find($id);

        //Updating Order
        if ($request->has('orderId')) {

            if ($pallet['orderId'] == null) {
                $updatedPallet->order_id = null;
            } else {
                if (Order::find($pallet['orderId']) == null) {
                    return response()->json(['errors' => ['orderId' => ['El pedido no existe']]], 422);
                } else {
                    $updatedPallet->order_id = $pallet['orderId'];
                }
            }
        }



        //Updating State
        if ($request->has('state')) {
            //echo '$updatedPallet->state_id = '.$updatedPallet->state_id . '!= $pallet[state][id] = '.$pallet["state"]["id"];
            if ($updatedPallet->state_id != $pallet['state']['id']) {
                // UnStoring pallet if it is in a store
                //echo '$updatedPallet->store ='. $updatedPallet->store. '!= null && $pallet[state][id] ='.$pallet['state']['id'].' != 2';
                if ($updatedPallet->store != null && $pallet['state']['id'] != 2) {
                    $updatedPallet->unStore();
                    //return response()->json(['errors' => ['state' => ['El palet se encuentra en un almacen, no se puede cambiar el estado']]], 422);
                }
                $updatedPallet->state_id = $pallet['state']['id'];
            }
        }

        //Updating Observations
        if ($request->has('observations')) {
            if ($pallet['observations'] != $updatedPallet->observations) {
                $updatedPallet->observations = $pallet['observations'];
            }
        }

        $updatedPallet->save();

        // Updating Store
        if (array_key_exists("store", $pallet)) {
            $storeId = $pallet['store']['id'] ?? null;

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
        if (array_key_exists("boxes", $pallet)) {
            $boxes = $pallet['boxes'];

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

        // return new PalletResource(Pallet::findOrFail($id));

        return response()->json(new PalletResource(Pallet::findOrFail($id)), 201);

        //return response()->json($updatedPallet->toArrayAssoc(), 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pallet = Pallet::findOrFail($id);
        $pallet->delete();

        return response()->json(['message' => 'Palet eliminado correctamente'], 200);
    }

    /* options */
    public function storedOptions()
    {

        /* $states = [
            ['id' => 1, 'name' => 'registered'],
            ['id' => 2, 'name' => 'stored'],
            ['id' => 3, 'name' => 'shipped'],
        ]; */

        $pallets = Pallet::select('id', 'id as name')
            ->where('state_id', 2)
            ->orderBy('id')
            ->get();

        return response()->json($pallets);
    }

    /* shippedOptions */
    public function shippedOptions()
    {

        /* $states = [
            ['id' => 1, 'name' => 'registered'],
            ['id' => 2, 'name' => 'stored'],
            ['id' => 3, 'name' => 'shipped'],
        ]; */
        /* id as name */
        $pallets = Pallet::select('id', 'id as name')
            ->where('state_id', 3)
            ->orderBy('id')
            ->get();

        return response()->json($pallets);
    }

    /* options */
    public function options()
    {
        $pallets = Pallet::select('id', 'id as name')
            ->orderBy('id')
            ->get();

        return response()->json($pallets);
    }

    public function assignToPosition(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'position_id' => 'required|integer|min:1',
            'pallet_ids' => 'required|array|min:1',
            'pallet_ids.*' => 'integer|exists:pallets,id',
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

        if ($pallet->state_id !== 2) {
            return response()->json(['error' => 'El palet no está en estado almacenado'], 400);
        }

        $storedPallet = StoredPallet::firstOrNew(['pallet_id' => $palletId]);
        $storedPallet->store_id = $storeId;
        $storedPallet->position = null; // ← resetea la posición al mover de almacén
        $storedPallet->save();

        return response()->json([
            'message' => 'Palet movido correctamente al nuevo almacén',
            'pallet' => new PalletResource($pallet->refresh()),
        ], 200);
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
            'state_id' => 'required|integer|exists:tenant.pallet_states,id',
            'ids' => 'array|required_without_all:filters,applyToAll',
            'ids.*' => 'integer|exists:tenant.pallets,id',
            'filters' => 'array|required_without_all:ids,applyToAll',
            'applyToAll' => 'boolean|required_without_all:ids,filters',
        ]);

        if ($validated->fails()) {
            return response()->json(['errors' => $validated->errors()], 422);
        }

        $stateId = $request->input('state_id');
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
            if ($pallet->state_id != $stateId) {
                if ($stateId !== 2 && $pallet->storedPallet) {
                    $pallet->unStore();
                }

                if ($stateId === 2 && !$pallet->storedPallet) {
                    StoredPallet::create([
                        'pallet_id' => $pallet->id,
                        'store_id' => 4, // puedes hacer dinámico
                    ]);
                }

                $pallet->state_id = $stateId;
                $pallet->save();
                $updatedCount++;
            }
        }

        return response()->json([
            'message' => 'Palets actualizados correctamente',
            'updated_count' => $updatedCount,
        ]);
    }









}
