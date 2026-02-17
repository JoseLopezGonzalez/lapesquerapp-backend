<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleCeboDispatchesRequest;
use App\Http\Requests\v2\IndexCeboDispatchRequest;
use App\Http\Requests\v2\StoreCeboDispatchRequest;
use App\Http\Requests\v2\UpdateCeboDispatchRequest;
use App\Http\Resources\v2\CeboDispatchResource;
use App\Models\CeboDispatch;
use App\Services\v2\CeboDispatchListService;
use Illuminate\Support\Facades\DB;

class CeboDispatchController extends Controller
{
    public function index(IndexCeboDispatchRequest $request)
    {
        return CeboDispatchResource::collection(CeboDispatchListService::list($request));
    }

    public function store(StoreCeboDispatchRequest $request)
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $dispatch = new CeboDispatch;
            $dispatch->supplier_id = (int) $validated['supplier']['id'];
            $dispatch->date = $validated['date'];
            $dispatch->notes = $validated['notes'] ?? null;
            $dispatch->save();

            foreach ($validated['details'] as $detail) {
                $dispatch->products()->create([
                    'product_id' => (int) $detail['product']['id'],
                    'net_weight' => (float) $detail['netWeight'],
                    'price' => isset($detail['price']) ? (float) $detail['price'] : null,
                ]);
            }

            $dispatch->load('supplier', 'products.product');

            return response()->json([
                'message' => 'Despacho de cebo creado correctamente.',
                'data' => new CeboDispatchResource($dispatch),
            ], 201);
        });
    }

    public function show($id)
    {
        $dispatch = CeboDispatch::with('supplier', 'products.product')->findOrFail($id);
        $this->authorize('view', $dispatch);

        return response()->json([
            'data' => new CeboDispatchResource($dispatch),
        ]);
    }

    public function update(UpdateCeboDispatchRequest $request, $id)
    {
        $validated = $request->validated();
        $dispatch = CeboDispatch::findOrFail($id);
        $this->authorize('update', $dispatch);

        return DB::transaction(function () use ($dispatch, $validated) {
            $dispatch->update([
                'supplier_id' => (int) $validated['supplier']['id'],
                'date' => $validated['date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $dispatch->products()->delete();
            foreach ($validated['details'] as $detail) {
                $dispatch->products()->create([
                    'product_id' => (int) $detail['product']['id'],
                    'net_weight' => (float) $detail['netWeight'],
                    'price' => isset($detail['price']) ? (float) $detail['price'] : null,
                ]);
            }

            $dispatch->load('supplier', 'products.product');

            return response()->json([
                'message' => 'Despacho de cebo actualizado correctamente.',
                'data' => new CeboDispatchResource($dispatch),
            ]);
        });
    }

    public function destroy($id)
    {
        $dispatch = CeboDispatch::findOrFail($id);
        $this->authorize('delete', $dispatch);

        // Eliminar el despacho - las líneas (cebo_dispatch_products) se eliminarán automáticamente
        // por cascade, pero los productos en sí NO se eliminarán
        $dispatch->delete();

        return response()->json(['message' => 'Despacho de cebo eliminado correctamente'], 200);
    }

    public function destroyMultiple(DestroyMultipleCeboDispatchesRequest $request)
    {
        $ids = $request->validated('ids');
        $dispatches = CeboDispatch::whereIn('id', $ids)->get();

        foreach ($dispatches as $dispatch) {
            $this->authorize('delete', $dispatch);
        }

        CeboDispatch::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Despachos de cebo eliminados correctamente']);
    }
}
