<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultiplePalletsRequest;
use App\Http\Requests\v2\IndexPalletRequest;
use App\Http\Requests\v2\StorePalletRequest;
use App\Http\Requests\v2\UpdatePalletRequest;
use App\Http\Resources\v2\PalletResource;
use App\Services\v2\PalletListService;
use App\Services\v2\PalletWriteService;
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

    public function index(IndexPalletRequest $request)
    {
        return PalletResource::collection(PalletListService::list($request));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePalletRequest $request)
    {
        $newPallet = PalletWriteService::store($request->validated());

        return response()->json(new PalletResource($newPallet), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $id))->firstOrFail();
        $this->authorize('view', $pallet);
        return response()->json([
            'data' => new PalletResource($pallet),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePalletRequest $request, string $id)
    {
        $pallet = Pallet::with('reception', 'boxes.box.productionInputs')->findOrFail($id);
        $this->authorize('update', $pallet);
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

        $updatedPallet = PalletWriteService::update($request, $pallet, $request->validated());

        return response()->json(new PalletResource($updatedPallet), 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pallet = Pallet::findOrFail($id);
        $this->authorize('delete', $pallet);
        
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
    public function destroyMultiple(DestroyMultiplePalletsRequest $request)
    {
        $validated = $request->validated();
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
        $this->authorize('viewAny', Pallet::class);
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
        $this->authorize('viewAny', Pallet::class);
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
        $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $pallet->id))->first();
        return response()->json([
            'message' => 'Palet movido correctamente al nuevo almacén',
            'pallet' => new PalletResource($pallet),
        ], 200);
    }

    public function moveMultipleToStore(Request $request)
    {
        $this->authorize('viewAny', Pallet::class);
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
        $pallet = Pallet::findOrFail($id);
        $this->authorize('update', $pallet);
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
        $this->authorize('viewAny', Pallet::class);
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
            $palletsQuery = PalletListService::applyFilters($palletsQuery, ['filters' => $request->input('filters')]);

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
        $pallet = Pallet::findOrFail($id);
        $this->authorize('update', $pallet);
        $validated = $request->validate([
            'orderId' => 'required|integer|exists:tenant.orders,id',
        ]);

        $pallet = Pallet::findOrFail($id);

        // Check if pallet is already linked to this order
        if ($pallet->order_id == $validated['orderId']) {
            $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $id))->first();
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

        $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $id))->first();
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
        $this->authorize('viewAny', Pallet::class);
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
        $this->authorize('update', $pallet);

        // Check if pallet is already unlinked from any order
        if (!$pallet->order_id) {
            $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $id))->first();
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

        $pallet = PalletListService::loadRelations(Pallet::query()->where('id', $id))->first();
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
        $this->authorize('viewAny', Pallet::class);
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
     * Buscar palets registrados por lote
     * Retorna solo palets que tienen cajas disponibles con el lote especificado
     */
    public function searchByLot(Request $request)
    {
        $this->authorize('viewAny', Pallet::class);
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
        $this->authorize('viewAny', Pallet::class);
        // Obtener todos los palets registrados (status = 1) con relaciones cargadas
        $query = Pallet::query()->where('status', Pallet::STATE_REGISTERED);
        $query = PalletListService::loadRelations($query);
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
     * @param int $orderId ID del pedido
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function availableForOrder(int $orderId, Request $request)
    {
        $this->authorize('viewAny', Pallet::class);
        // Validar que el pedido existe
        \Validator::make(['orderId' => $orderId], [
            'orderId' => 'required|integer|exists:tenant.orders,id',
        ])->validate();
        
        $validated = $request->validate([
            'ids' => 'nullable|array', // Filtro por múltiples IDs específicos
            'ids.*' => 'integer', // Cada ID debe ser un entero
            'storeId' => 'nullable|integer|exists:tenant.stores,id',
            'perPage' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        $idsFilter = $validated['ids'] ?? null;
        $storeId = $validated['storeId'] ?? null;
        $perPage = $validated['perPage'] ?? 20;
        
        // orderId viene de la ruta, siempre está presente

        // Construir query base
        $query = Pallet::query()
            ->whereIn('status', [Pallet::STATE_REGISTERED, Pallet::STATE_STORED])
            ->with([
                'boxes.box.product',
                'boxes.box.productionInputs', // Para calcular disponibilidad
                'storedPallet.store',
                'reception'
            ]);

        // Filtrar por IDs específicos si se proporcionan
        // ids tiene prioridad absoluta sobre storeId: si un ID está en ids, se muestra aunque no cumpla storeId
        if ($idsFilter && is_array($idsFilter) && !empty($idsFilter)) {
            // Filtrar por los IDs proporcionados (solo los que existen se devolverán)
            $query->whereIn('id', $idsFilter);
            // Nota: storeId se ignora cuando hay ids porque ids tiene prioridad absoluta
        } else {
            // Si no hay ids, aplicar filtro de almacén normalmente
            
            // Filtrar por almacén si se proporciona storeId
            if ($storeId !== null) {
                // Solo incluir palets que tienen storedPallet con el store_id especificado
                $query->whereHas('storedPallet', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                });
            }
        }

        // Excluir palets vinculados a otros pedidos
        // Incluir palets sin pedido O del mismo pedido (para permitir cambio de palets)
        $query->where(function ($q) use ($orderId) {
            $q->whereNull('order_id')
              ->orWhere('order_id', $orderId);
        });

        // Ordenar por ID descendente
        $query->orderBy('id', 'desc');

        // Paginación
        $pallets = $query->paginate($perPage);

        // Formatear respuesta con datos relevantes
        $formattedPallets = $pallets->getCollection()->map(function ($pallet) {
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
                    'store_name' => $pallet->storedPallet->store ? $pallet->storedPallet->store->name : null,
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
