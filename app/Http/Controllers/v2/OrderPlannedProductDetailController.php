<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\StoreOrderPlannedProductDetailRequest;
use App\Http\Requests\v2\UpdateOrderPlannedProductDetailRequest;
use App\Http\Resources\v2\OrderPlannedProductDetailResource;
use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use Illuminate\Http\Request;

class OrderPlannedProductDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $query = OrderPlannedProductDetail::with(['product', 'tax', 'order']);

        if ($request->has('orderId')) {
            $query->where('order_id', $request->orderId);
        }

        if ($request->has('productId')) {
            $query->where('product_id', $request->productId);
        }

        $query->orderBy('id', 'desc');

        $perPage = $request->input('perPage', 15);
        $details = $query->paginate($perPage);

        return OrderPlannedProductDetailResource::collection($details);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {


    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderPlannedProductDetailRequest $request)
    {
        $order = Order::findOrFail($request->validated('orderId'));
        $this->authorize('update', $order);

        $validated = $request->validated();
        $orderPlannedProductDetail = OrderPlannedProductDetail::create([
            'order_id' => $validated['orderId'],
            'product_id' => $validated['product']['id'],
            'tax_id' => $validated['tax']['id'],
            'quantity' => $validated['quantity'],
            'boxes' => $validated['boxes'],
            'unit_price' => $validated['unitPrice'],
            'line_base' => $validated['unitPrice'] * $validated['quantity'],
            'line_total' => $validated['unitPrice'] * $validated['quantity'],
        ]);

        return response()->json([
            'message' => 'Detalle de producto planificado creado correctamente.',
            'data' => new OrderPlannedProductDetailResource($orderPlannedProductDetail),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $detail = OrderPlannedProductDetail::with(['product', 'tax', 'order'])->findOrFail($id);
        $this->authorize('view', $detail->order);

        return response()->json([
            'data' => new OrderPlannedProductDetailResource($detail),
        ]);
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
    public function update(UpdateOrderPlannedProductDetailRequest $request, string $id)
    {
        $orderPlannedProductDetail = OrderPlannedProductDetail::findOrFail($id);
        $this->authorize('update', $orderPlannedProductDetail->order);

        $validated = $request->validated();
        $orderPlannedProductDetail->update([
            'product_id' => $validated['product']['id'],
            'tax_id' => $validated['tax']['id'],
            'quantity' => $validated['quantity'],
            'boxes' => $validated['boxes'],
            'unit_price' => $validated['unitPrice'],
            'line_base' => $validated['unitPrice'] * $validated['quantity'],
            'line_total' => $validated['unitPrice'] * $validated['quantity'],
        ]);

        return response()->json([
            'message' => 'Detalle de producto planificado actualizado correctamente.',
            'data' => new OrderPlannedProductDetailResource($orderPlannedProductDetail),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $orderPlannedProductDetail = OrderPlannedProductDetail::findOrFail($id);
        $this->authorize('update', $orderPlannedProductDetail->order);

        $orderPlannedProductDetail->delete();

        return response()->json(['message' => 'Linea eliminada correctamente'], 200);
    }



}
