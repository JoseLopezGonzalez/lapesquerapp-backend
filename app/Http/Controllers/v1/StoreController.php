<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Store;
use App\Http\Resources\v1\StoreDetailsResource;
use App\Http\Resources\v1\StoreResource;

class StoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        /* $stores = Store::all()->map->toSimpleArray();

    return ['data' => $stores]; */

    /* utilizando collection */
    return StoreResource::collection(Store::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $store = Store::create($request->all());
        return response()->json($store, 201);
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
        
        return new StoreDetailsResource($store);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $store = Store::findOrFail($id);
        $store->update($request->all());
        return response()->json($store, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Store::destroy($id);
        return response()->json(null, 204);
    }
}
