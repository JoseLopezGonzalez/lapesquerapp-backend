<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\v1\PalletResource;
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

    public function index(Request $request)
    {
        $query = Pallet::query();

        $query->with('storedPallet');

        if ($request->has('id')) {
            $id = $request->input('id');
            $query->where('id', 'like', "%{$id}%");
        }

        if ($request->has('state')) {
            $state = $request->input('state');
            if ($state == 'registered') {
                $query->where('status', Pallet::STATE_REGISTERED);
            } else if ($state == 'stored') {
                $query->where('status', Pallet::STATE_STORED);
            } else if ($state == 'shipped') {
                $query->where('status', Pallet::STATE_SHIPPED);
            } else if ($state == 'processed') {
                $query->where('status', Pallet::STATE_PROCESSED);
            }
        }

        // Filtro por estado de la orden (pending o finished)
        if ($request->has('orderState')) {
            $orderState = $request->input('orderState');

            if ($orderState === 'pending') {
                $query->whereHas('order', function ($subQuery) {
                    $subQuery->where('status', 'pending');
                });
            } elseif ($orderState === 'finished') {
                $query->whereHas('order', function ($subQuery) {
                    $subQuery->where('status', 'finished');
                });
            } elseif ($orderState === 'without_order') {
                // Filtrar pallets que no tienen ninguna orden asociada
                $query->whereDoesntHave('order');
            }
        }



        /* Position */
        if ($request->has('position')) {
            if ($request->input('position') == 'located') {
                $query->whereHas('storedPallet', function ($subQuery) {
                    $subQuery->whereNotNull('position');
                });
            } else if ($request->input('position') == 'unlocated') {
                $query->whereHas('storedPallet', function ($subQuery) {
                    $subQuery->whereNull('position');
                });
            }
        }

        /* Dates */

        if ($request->has('dates')) {

            $dates = $request->input('dates');

            if (isset($dates['start'])) {
                $startDate = $dates['start'];
                $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                $query->where('created_at', '>=', $startDate);
            }

            if (isset($dates['end'])) {
                $endDate = $dates['end'];
                $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                $query->where('created_at', '<=', $endDate);
            }
        }

        if ($request->has('notes')) {
            $notes = $request->input('notes');
            $query->where('observations', 'like', "%{$notes}%");
        }

        if ($request->has('lots')) {
            $lots = $request->input('lots');
            $query->whereHas('boxes', function ($subQuery) use ($lots) {
                $subQuery->whereHas('box', function ($subSubQuery) use ($lots) {
                    $subSubQuery->whereIn('lot', $lots);
                });
            });
        }

        if ($request->has('products')) {
            $articles = $request->input('products');
            $query->whereHas('boxes', function ($subQuery) use ($articles) {
                $subQuery->whereHas('box', function ($subSubQuery) use ($articles) {
                    $subSubQuery->whereIn('article_id', $articles);
                });
            });
        }

        if ($request->has('weights')) {
            $weights = $request->input('weights');
            if (array_key_exists('netWeight', $weights)) {
                if (array_key_exists('min', $weights['netWeight'])) {
                    $query->whereHas('boxes', function ($subQuery) use ($weights) {
                        $subQuery->whereHas('box', function ($subSubQuery) use ($weights) {
                            $subSubQuery->havingRaw('sum(net_weight) >= ?', [$weights['netWeight']['min']]);
                        });
                    });
                }
                if (array_key_exists('max', $weights['netWeight'])) {
                    $query->whereHas('boxes', function ($subQuery) use ($weights) {
                        $subQuery->whereHas('box', function ($subSubQuery) use ($weights) {
                            $subSubQuery->havingRaw('sum(net_weight) <= ?', [$weights['netWeight']['max']]);
                        });
                    });
                }
            }
            if (array_key_exists('grossWeight', $weights)) {
                if (array_key_exists('min', $weights['grossWeight'])) {
                    $query->whereHas('boxes', function ($subQuery) use ($weights) {
                        $subQuery->whereHas('box', function ($subSubQuery) use ($weights) {
                            $subSubQuery->havingRaw('sum(gross_weight) >= ?', [$weights['grossWeight']['min']]);
                        });
                    });
                }
                if (array_key_exists('max', $weights['grossWeight'])) {
                    $query->whereHas('boxes', function ($subQuery) use ($weights) {
                        $subQuery->whereHas('box', function ($subSubQuery) use ($weights) {
                            $subSubQuery->havingRaw('sum(gross_weight) <= ?', [$weights['grossWeight']['max']]);
                        });
                    });
                }
            }
        }






        $perPage = $request->input('perPage', 10); // Default a 10 si no se proporciona
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


        //Insertando Palet
        $newPallet = new Pallet;
        $newPallet->observations = $pallet['observations'];
        $newPallet->status = Pallet::STATE_REGISTERED; // Siempre estado registrado.
        $newPallet->save();

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

        return response()->json($newPallet->toArrayAssoc(), 201);
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
            'store' => 'sometimes|nullable|integer',
            'state.id' => 'sometimes|integer',
            'boxes' => 'sometimes|array',
            'boxes.*.id' => 'sometimes|nullable|integer',
            'boxes.*.article.id' => 'required_with:boxes|integer',
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



        $pallet = $request->all();

        //Creating Pallet
        $updatedPallet = Pallet::find($id);

        //Updating Order
        $wasUnlinked = false;
        if ($request->has('orderId')) {

            if ($pallet['orderId'] == null && $updatedPallet->order_id !== null) {
                $updatedPallet->order_id = null;
                $wasUnlinked = true;
            } else {
                // La validación ya verifica que el orderId existe si no es null
                $updatedPallet->order_id = $pallet['orderId'];
            }
        }



        //Updating State
        $stateWasManuallyChanged = false;
        if ($request->has('state')) {
            //echo '$updatedPallet->status = '.$updatedPallet->status . '!= $pallet[state][id] = '.$pallet["state"]["id"];
            if ($updatedPallet->status != $pallet['state']['id']) {
                // UnStoring pallet if it is in a store
                //echo '$updatedPallet->store ='. $updatedPallet->store. '!= null && $pallet[state][id] ='.$pallet['state']['id'].' != 2';
                if ($updatedPallet->store != null && $pallet['state']['id'] != Pallet::STATE_STORED) {
                    $updatedPallet->unStore();
                    //return response()->json(['errors' => ['state' => ['El palet se encuentra en un almacen, no se puede cambiar el estado']]], 422);
                }
                $updatedPallet->status = $pallet['state']['id'];
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
            if ($pallet['observations'] != $updatedPallet->observations) {
                $updatedPallet->observations = $pallet['observations'];
            }
        }

        $updatedPallet->save();

        // Updating Store
        if (array_key_exists("store", $pallet)) {
            $storeId = $pallet['store'];

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

                        $box->box->article_id = $updatedBox['article']['id'];
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
                $newBox->article_id = $box['article']['id'];
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
        
        // Validar que no se pueda eliminar un palet de recepción
        if ($pallet->reception_id !== null) {
            return response()->json([
                'error' => 'No se puede eliminar un palet que proviene de una recepción. Elimine la recepción o modifique desde la recepción.'
            ], 403);
        }
        
        $pallet->delete();

        return response()->json(['message' => 'Palet eliminado correctamente'], 200);
    }
}
