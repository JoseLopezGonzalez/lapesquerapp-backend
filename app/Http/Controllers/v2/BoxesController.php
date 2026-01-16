<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\BoxResource;
use App\Models\Box;
use App\Models\Transport;
use Illuminate\Http\Request;

class BoxesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Cargar relaciones necesarias para determinar disponibilidad
        $query = Box::with(['productionInputs.productionRecord.production', 'product']);

        // Filtro para solo cajas disponibles (no usadas en producción)
        if ($request->has('available') && $request->available === 'true') {
            $query->whereDoesntHave('productionInputs');
        }

        // Filtro para solo cajas usadas en producción
        if ($request->has('available') && $request->available === 'false') {
            $query->whereHas('productionInputs');
        }

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        /*  product.article.name*/
        if ($request->has('name')) {
            $query->whereHas('product', function ($query) use ($request) {
                $query->whereHas('article', function ($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->name . '%');
                });
            });
        }

        /* product.species Where in*/
        if ($request->has('species')) {
            $query->whereHas('product', function ($query) use ($request) {
                $query->whereIn('species_id', $request->species);
            });
        }

        /* lot where in*/
        if ($request->has('lots')) {
            $query->whereIn('lot', $request->lots);
        }

        /* products where in */
        if ($request->has('products')) {
            $query->whereIn('article_id', $request->products);
        }

        /* palletIds */
        if ($request->has('pallets')) {
            $query->whereHas('palletBox', function ($query) use ($request) {
                $query->whereIn('pallet_id', $request->pallets);
            });
        }

        /* gs1128 where in */
        if ($request->has('gs1128')) {
            $query->whereIn('gs1_128', $request->gs1128);
        }

        /* createdAt */
        if ($request->has('createdAt')) {
            $createdAt = $request->input('createdAt');
            /* Check if $createdAt['start'] exists */
            if (isset($createdAt['start'])) {
                $startDate = $createdAt['start'];
                $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                $query->where('created_at', '>=', $startDate);
            }
            /* Check if $createdAt['end'] exists */
            if (isset($createdAt['end'])) {
                $endDate = $createdAt['end'];
                $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                $query->where('created_at', '<=', $endDate);
            }
        }

        /* palletState - Filtro por estado del pallet */
        if ($request->has('palletState')) {
            if ($request->palletState === 'stored') {
                $query->whereHas('palletBox.pallet', function ($query) {
                    $query->where('status', \App\Models\Pallet::STATE_STORED);
                });
            } elseif ($request->palletState === 'shipped') {
                $query->whereHas('palletBox.pallet', function ($query) {
                    $query->where('status', \App\Models\Pallet::STATE_SHIPPED);
                });
            }
        }

        /* orderState - Filtro por estado de la orden del pallet */
        if ($request->has('orderState')) {
            $orderStates = is_array($request->orderState) ? $request->orderState : [$request->orderState];
            
            // Manejar el caso especial de 'without_order'
            if (in_array('without_order', $orderStates)) {
                $orderStates = array_filter($orderStates, function($state) {
                    return $state !== 'without_order';
                });
                
                if (!empty($orderStates)) {
                    // Si hay otros estados además de 'without_order', usar OR
                    $query->where(function($query) use ($orderStates) {
                        $query->whereHas('palletBox.pallet.order', function ($query) use ($orderStates) {
                            $query->whereIn('status', $orderStates);
                        })->orWhereHas('palletBox.pallet', function ($query) {
                            $query->whereDoesntHave('order');
                        });
                    });
                } else {
                    // Solo 'without_order'
                    $query->whereHas('palletBox.pallet', function ($query) {
                        $query->whereDoesntHave('order');
                    });
                }
            } else {
                // Solo estados normales
                $query->whereHas('palletBox.pallet.order', function ($query) use ($orderStates) {
                    $query->whereIn('status', $orderStates);
                });
            }
        }

        /* position - Filtro por posición del pallet */
        if ($request->has('position')) {
            if ($request->position === 'located') {
                $query->whereHas('palletBox.pallet.storedPallet', function ($query) {
                    $query->whereNotNull('position');
                });
            } elseif ($request->position === 'unlocated') {
                $query->whereHas('palletBox.pallet.storedPallet', function ($query) {
                    $query->whereNull('position');
                });
            }
        }

        /* stores - Filtro por almacén donde está el pallet */
        if ($request->has('stores')) {
            $stores = is_array($request->stores) ? $request->stores : explode(',', $request->stores);
            $query->whereHas('palletBox.pallet.storedPallet', function ($query) use ($stores) {
                $query->whereIn('store_id', $stores);
            });
        }

        /* orders - Filtro por órdenes asociadas al pallet */
        if ($request->has('orders')) {
            $query->whereHas('palletBox.pallet.order', function ($query) use ($request) {
                $query->whereIn('order_id', $request->orders);
            });
        }

        /* notes - Filtro por observaciones del pallet */
        if ($request->has('notes')) {
            $query->whereHas('palletBox.pallet', function ($query) use ($request) {
                $query->where('observations', 'like', '%' . $request->notes . '%');
            });
        }

        /* orderIds - Filtro por IDs de pedidos */
        if ($request->has('orderIds')) {
            $orderIds = is_array($request->orderIds) ? $request->orderIds : explode(',', $request->orderIds);
            $query->whereHas('palletBox.pallet.order', function ($query) use ($orderIds) {
                $query->whereIn('id', $orderIds);
            });
        }

        /* orderDates - Filtro por fechas de pedidos */
        if ($request->has('orderDates')) {
            $orderDates = $request->input('orderDates');
            if (isset($orderDates['start'])) {
                $startDate = $orderDates['start'];
                $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                $query->whereHas('palletBox.pallet.order', function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                });
            }
            if (isset($orderDates['end'])) {
                $endDate = $orderDates['end'];
                $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                $query->whereHas('palletBox.pallet.order', function ($query) use ($endDate) {
                    $query->where('created_at', '<=', $endDate);
                });
            }
        }

        /* orderBuyerReference - Filtro por referencia de compra */
        if ($request->has('orderBuyerReference')) {
            $query->whereHas('palletBox.pallet.order', function ($query) use ($request) {
                $query->where('buyer_reference', 'like', '%' . $request->orderBuyerReference . '%');
            });
        }

        /* order by id desc */
        $query->orderBy('id', 'desc');

        /* no filter more */
        $perPage = $request->input('perPage', 12); // Default a 12 si no se proporciona
        return BoxResource::collection($query->paginate($perPage));
    }

    /**
     * Obtener cajas disponibles para un proceso de producción específico
     * Útil para el frontend al seleccionar cajas para producción
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function available(Request $request)
    {
        $query = Box::query()
            ->whereDoesntHave('productionInputs') // Solo cajas disponibles
            ->with(['product', 'palletBox.pallet']);

        // Filtros opcionales para refinar la búsqueda
        if ($request->has('lot')) {
            $query->where('lot', $request->lot);
        }

        if ($request->has('product_id')) {
            $query->where('article_id', $request->product_id);
        }

        if ($request->has('product_ids')) {
            $query->whereIn('article_id', $request->product_ids);
        }

        if ($request->has('pallet_id')) {
            $query->whereHas('palletBox', function ($q) use ($request) {
                $q->where('pallet_id', $request->pallet_id);
            });
        }

        if ($request->has('pallet_ids')) {
            $query->whereHas('palletBox', function ($q) use ($request) {
                $q->whereIn('pallet_id', $request->pallet_ids);
            });
        }

        // Solo cajas que están en palets almacenados (status = 2)
        if ($request->has('onlyStored') && $request->onlyStored === 'true') {
            $query->whereHas('palletBox.pallet', function ($q) {
                $q->where('status', \App\Models\Pallet::STATE_STORED);
            });
        }

        /* stores - Filtro por almacén donde está el pallet */
        if ($request->has('stores')) {
            $stores = is_array($request->stores) ? $request->stores : explode(',', $request->stores);
            $query->whereHas('palletBox.pallet.storedPallet', function ($q) use ($stores) {
                $q->whereIn('store_id', $stores);
            });
        }

        $perPage = $request->input('perPage', 50);
        $boxes = $query->orderBy('id', 'desc')->paginate($perPage);

        return BoxResource::collection($boxes);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $box = Box::findOrFail($id);
        $box->delete();

        return response()->json(['message' => 'Caja eliminada con éxito']);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'No se han proporcionado IDs válidos'], 400);
        }

        Box::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Cajas eliminadas con éxito']);
    }

    
}
