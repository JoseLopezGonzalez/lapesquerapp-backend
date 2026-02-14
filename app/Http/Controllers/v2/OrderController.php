<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleOrdersRequest;
use App\Http\Requests\v2\StoreOrderRequest;
use App\Http\Requests\v2\UpdateOrderRequest;
use App\Http\Requests\v2\UpdateOrderStatusRequest;
use App\Http\Resources\v2\ActiveOrderCardResource;
use App\Http\Resources\v2\OrderDetailsResource;
use App\Http\Resources\v2\OrderResource;
use App\Models\Order;
use App\Services\v2\OrderDetailService;
use App\Services\v2\OrderListService;
use App\Services\v2\OrderProductionViewService;
use App\Services\v2\OrderStatisticsService;
use App\Services\v2\OrderStoreService;
use App\Services\v2\OrderUpdateService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        return OrderResource::collection(OrderListService::list($request));
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
    public function store(StoreOrderRequest $request)
    {
        $this->authorize('create', Order::class);

        try {
            $order = OrderStoreService::store($request->validated());
            return response()->json([
                'message' => 'Pedido creado correctamente.',
                'data' => new OrderDetailsResource($order),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el pedido',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $order = OrderDetailService::getOrderForDetail($id);
        $this->authorize('view', $order);
        return new OrderDetailsResource($order);
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
    public function update(UpdateOrderRequest $request, string $id)
    {
        $order = Order::with([
            'pallets.boxes.box.productionInputs',
            'pallets.boxes.box.product',
        ])->findOrFail($id);

        $this->authorize('update', $order);

        try {
            $order = OrderUpdateService::update($order, $request->validated());
            return response()->json([
                'message' => 'Pedido actualizado correctamente.',
                'data' => new OrderDetailsResource($order),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
                'userMessage' => 'La fecha de carga debe ser mayor o igual a la fecha de entrada.',
            ], 422);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);

        $this->authorize('delete', $order);

        // Validar si el pedido está en uso antes de eliminar
        $usedInPallets = $order->pallets()->exists();

        if ($usedInPallets) {
            return response()->json([
                'message' => 'No se puede eliminar el pedido porque está en uso',
                'details' => 'El pedido está siendo utilizado en palets',
                'userMessage' => 'No se puede eliminar el pedido porque está siendo utilizado en palets'
            ], 400);
        }

        $order->delete();
        return response()->json(['message' => 'Pedido eliminado correctamente'], 200);
    }

    public function destroyMultiple(DestroyMultipleOrdersRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $validated = $request->validated();
        $orders = Order::whereIn('id', $validated['ids'])->get();
        
        // Validar si alguno de los pedidos está en uso
        $inUse = [];
        foreach ($orders as $order) {
            $usedInPallets = $order->pallets()->exists();
            
            if ($usedInPallets) {
                $inUse[] = [
                    'id' => $order->id,
                    'formattedId' => $order->formatted_id ?? '#' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
                ];
            }
        }

        if (!empty($inUse)) {
            $message = 'No se pueden eliminar algunos pedidos porque están en uso: ';
            $details = array_map(function($item) {
                return $item['formattedId'] . ' (usado en palets)';
            }, $inUse);
            
            return response()->json([
                'message' => 'No se pueden eliminar algunos pedidos porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => $message . implode(', ', array_column($inUse, 'formattedId'))
            ], 400);
        }

        Order::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Pedidos eliminados correctamente']);
    }

    /* Options */
    public function options()
    {
        $this->authorize('viewAny', Order::class);

        return response()->json(OrderListService::options());
    }

    /**
     * List active orders for Order Manager (tarjetas: estado, id, cliente, fecha de carga).
     */
    public function active()
    {
        $this->authorize('viewAny', Order::class);

        return ActiveOrderCardResource::collection(OrderListService::active());
    }

    /* Active Orders Options */
    public function activeOrdersOptions()
    {
        $this->authorize('viewAny', Order::class);

        return response()->json(OrderListService::activeOrdersOptions());
    }


    /* update Order status */
    public function updateStatus(UpdateOrderStatusRequest $request, string $id)
    {
        $order = Order::with([
            'pallets.boxes.box.productionInputs',
            'pallets.boxes.box.product',
        ])->findOrFail($id);

        $this->authorize('update', $order);

        $previousStatus = $order->status;
        $status = $request->validated()['status'];
        $order->status = $status;
        $order->save();

        // Si el pedido cambia a 'finished', cambiar todos los palets a 'shipped'
        if ($status === 'finished' && $previousStatus !== 'finished') {
            foreach ($order->pallets as $pallet) {
                $pallet->changeToShipped();
            }
        }

        // Recargar relaciones después de actualizar (species.fishingGear para toArrayAssoc del producto)
        $order->load([
            'pallets.boxes.box.productionInputs',
            'pallets.boxes.box.product.species.fishingGear',
        ]);

        return response()->json([
            'message' => 'Estado del pedido actualizado correctamente.',
            'data' => new OrderDetailsResource($order),
        ]);
    }




    public function salesBySalesperson(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        try {
            $validated = $request->validate([
                'dateFrom' => 'required|date',
                'dateTo' => 'required|date',
            ]);

            $data = OrderStatisticsService::getSalesBySalesperson(
                $validated['dateFrom'],
                $validated['dateTo']
            );

            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('Error in salesBySalesperson: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Error processing request: ' . $e->getMessage()], 500);
        }
    }

    /* Ojo, calcula la comparacion mismo rango de fechas pero un año atrás */






    public function transportChartData(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        try {
            $validated = $request->validate([
                'dateFrom' => 'required|date',
                'dateTo' => 'required|date|after_or_equal:dateFrom',
            ]);

            $result = OrderStatisticsService::getTransportChartData(
                $validated['dateFrom'],
                $validated['dateTo']
            );

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error in transportChartData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Error processing request: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Vista de producción - Pedidos agrupados por producto (día actual).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function productionView()
    {
        $this->authorize('viewAny', Order::class);

        try {
            return response()->json(OrderProductionViewService::getData());
        } catch (\Exception $e) {
            \Log::error('Error in productionView: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error processing request: ' . $e->getMessage(),
            ], 500);
        }
    }



















}
