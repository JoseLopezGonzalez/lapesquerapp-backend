<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\OrderPlannedProductDetailResource;
use App\Models\OrderPlannedProductDetail;
use Illuminate\Http\Request;

class OrderPlannedProductDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = OrderPlannedProductDetail::with(['product', 'tax', 'order']);

        // Filtro por orderId
        if ($request->has('orderId')) {
            $query->where('order_id', $request->orderId);
        }

        // Filtro por productId
        if ($request->has('productId')) {
            $query->where('product_id', $request->productId);
        }

        // Ordenar por ID descendente
        $query->orderBy('id', 'desc');

        // Paginación
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
    public function store(Request $request)
    {
        $request->validate([
            "orderId" => 'required|integer|exists:tenant.orders,id',
            "boxes" => 'required|integer',
            "product.id" => 'required|integer|exists:tenant.products,id',
            "quantity" => 'required|numeric',
            "tax.id" => 'required|integer|exists:tenant.taxes,id',
            'unitPrice' => 'required|numeric',
        ]);

        $orderPlannedProductDetail = OrderPlannedProductDetail::create([
            'order_id' => $request->orderId,
            'product_id' => $request->product['id'],
            'tax_id' => $request->tax['id'],
            'quantity' => $request->quantity,
            'boxes' => $request->boxes,
            'unit_price' => $request->unitPrice,
            'line_base' => $request->unitPrice * $request->quantity,
            'line_total' => $request->unitPrice * $request->quantity,
        ]);

        return new OrderPlannedProductDetailResource($orderPlannedProductDetail);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $detail = OrderPlannedProductDetail::with(['product', 'tax', 'order'])->findOrFail($id);
        return new OrderPlannedProductDetailResource($detail);
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

        $request->validate([
            "boxes" => 'required|integer',
            "product.id" => 'required|integer|exists:tenant.products,id',
            "quantity" => 'required|numeric',
            "tax.id" => 'required|integer|exists:tenant.taxes,id',
            'unitPrice' => 'required|numeric',
        ]);

        $orderPlannedProductDetail = OrderPlannedProductDetail::findOrFail($id);
        $orderPlannedProductDetail->update([
            'product_id' => $request->product['id'],
            'tax_id' => $request->tax['id'],
            'quantity' => $request->quantity,
            'boxes' => $request->boxes,
            'unit_price' => $request->unitPrice,
            'line_base' => $request->unitPrice * $request->quantity,
            'line_total' => $request->unitPrice * $request->quantity,
        ]);

        return new OrderPlannedProductDetailResource($orderPlannedProductDetail);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $orderPlannedProductDetail = OrderPlannedProductDetail::findOrFail($id);
        $orderPlannedProductDetail->delete();
        return response()->json(['message' => 'Linea eliminada correctamente'], 200);
    }



}
