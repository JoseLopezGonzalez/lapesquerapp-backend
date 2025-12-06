<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use \App\Models\Pallet;
use \App\Models\Box;
use App\Models\PalletBox;
use App\Models\StoredPallet;
use Illuminate\Support\Facades\Validator;


class StoredPalletController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'observations' => 'nullable|string',
            'storeId' => 'required|numeric',
            'boxes' => 'required|array',
            'boxes.*.article.id' => 'required|integer',
            'boxes.*.lot' => 'required|string',
            'boxes.*.gs1128' => 'required|string',
            'boxes.*.grossWeight' => 'required|numeric',
            'boxes.*.netWeight' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Código de estado 422 - Unprocessable Entity
        }


        $pallet = $request->all();
        $boxes = $pallet['boxes'];
        $storeId = $pallet['storeId'];

        //Insertando Palet
        $newPallet = new Pallet;
        $newPallet->observations = $pallet['observations'];
        $newPallet->state_id = 2; // Siempre estado almacenado.
        $newPallet->save();

        //Agregando Palet a almacen
        $newStoredPallet = new StoredPallet;
        $newStoredPallet->pallet_id = $newPallet->id;
        $newStoredPallet->store_id = $storeId;
        $newStoredPallet->save();

        //Insertando Cajas
        foreach ($boxes as $box) {
            $newBox = new Box;
            $newBox->article_id = $box['article']['id'];
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

        return response()->json($newStoredPallet->toArrayAssoc(), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if ($request->has('position')) {
            // Es una solicitud para actualizar la posición del palet
            $validator = Validator::make($request->all(), [
                'position' => 'nullable|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422); // Código de estado 422 - Unprocessable Entity
            }

            $position = $request->all()['position'];

            $storedPallet = StoredPallet::where('pallet_id', $id)->first();
            $storedPallet->position = $position;
            $storedPallet->save();



            return response()->json($storedPallet->toArrayAssoc(), 200);

            // Lógica para actualizar la posición del palet
        } else {
            
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $storedPallet = StoredPallet::find($id);
        $storedPallet->delete();

        return response()->json(null, 204);
    }
}
