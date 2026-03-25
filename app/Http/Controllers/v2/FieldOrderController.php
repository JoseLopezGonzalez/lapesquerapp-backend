<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexFieldOrderRequest;
use App\Http\Requests\v2\StoreFieldAutoventaRequest;
use App\Http\Requests\v2\UpdateOperationalOrderRequest;
use App\Http\Resources\v2\FieldOrderDetailsResource;
use App\Http\Resources\v2\FieldOrderResource;
use App\Models\FieldOperator;
use App\Models\Order;
use App\Services\v2\OperationalOrderExecutionService;
use App\Services\v2\OrderDetailService;
use App\Services\v2\OrderStoreService;
use Illuminate\Http\Request;

class FieldOrderController extends Controller
{
    private function getCurrentFieldOperatorId(Request $request): int
    {
        $user = $request->user();
        $fieldOperatorId = $user?->fieldOperator?->id
            ?? ($user ? FieldOperator::query()->where('user_id', $user->id)->value('id') : null);

        abort_unless($fieldOperatorId !== null, 403, 'No tienes una identidad operativa activa para acceder a este recurso.');

        return $fieldOperatorId;
    }

    public function index(IndexFieldOrderRequest $request)
    {
        $fieldOperatorId = $this->getCurrentFieldOperatorId($request);

        $query = Order::query()
            ->with([
                'customer:id,name',
                'plannedProductDetails.product',
                'plannedProductDetails.tax',
            ])
            ->where('field_operator_id', $fieldOperatorId);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('orderType')) {
            $query->where('order_type', $request->input('orderType'));
        }

        if ($request->filled('routeId')) {
            $query->where('route_id', $request->integer('routeId'));
        }

        return FieldOrderResource::collection(
            $query->orderByDesc('load_date')->paginate($request->input('perPage', 10))
        );
    }

    public function show(Order $order)
    {
        $this->authorize('viewOperational', $order);

        return response()->json([
            'data' => new FieldOrderDetailsResource(OrderDetailService::getOrderForDetail((string) $order->id)),
        ]);
    }

    public function update(UpdateOperationalOrderRequest $request, Order $order)
    {
        $this->authorize('updateOperational', $order);

        $updated = OperationalOrderExecutionService::execute($order, $request->validated());

        return response()->json([
            'message' => 'Pedido operativo actualizado correctamente.',
            'data' => new FieldOrderDetailsResource($updated),
        ]);
    }

    public function storeAutoventa(StoreFieldAutoventaRequest $request)
    {
        $this->getCurrentFieldOperatorId($request);

        $validated = $request->validated();
        $validated['orderType'] = Order::ORDER_TYPE_AUTOVENTA;

        $order = OrderStoreService::store($validated, $request->user());
        $order = OrderDetailService::getOrderForDetail((string) $order->id);

        return response()->json([
            'message' => 'Autoventa registrada correctamente.',
            'data' => new FieldOrderResource($order),
        ], 201);
    }
}
