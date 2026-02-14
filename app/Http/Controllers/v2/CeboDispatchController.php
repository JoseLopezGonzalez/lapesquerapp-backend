<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleCeboDispatchesRequest;
use App\Http\Requests\v2\IndexCeboDispatchRequest;
use App\Http\Requests\v2\StoreCeboDispatchRequest;
use App\Http\Requests\v2\UpdateCeboDispatchRequest;
use App\Http\Resources\v2\CeboDispatchResource;
use App\Models\CeboDispatch;
use App\Models\CeboDispatchProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CeboDispatchController extends Controller
{
    public function index(IndexCeboDispatchRequest $request)
    {
        $query = CeboDispatch::query();
        $query->with('supplier', 'products.product');

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        if ($request->has('suppliers')) {
            $query->whereIn('supplier_id', $request->suppliers);
        }

        if ($request->has('dates')) {
            $dates = $request->input('dates');
            /* Check if $dates['start'] exists */
            if (isset($dates['start'])) {
                $startDate = $dates['start'];
                $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                $query->where('date', '>=', $startDate);
            }
            /* Check if $dates['end'] exists */
            if (isset($dates['end'])) {
                $endDate = $dates['end'];
                $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                $query->where('date', '<=', $endDate);
            }
        }


        if ($request->has('species')) {
            $query->whereHas('products.product', function ($query) use ($request) {
                $query->whereIn('species_id', $request->species);
            });
        }

        if ($request->has('products')) {
            $query->whereHas('products.product', function ($query) use ($request) {
                $query->whereIn('id', $request->products);
            });
        }

        if ($request->has('notes')) {
            $query->where('notes', 'like', '%' . $request->notes . '%');
        }

        if ($request->has('export_type')) {
            $query->where('export_type', $request->export_type);
        }

        /* Order by Date Descen */
        $query->orderBy('date', 'desc');

        $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
        return CeboDispatchResource::collection($query->paginate($perPage));
    }

    public function store(StoreCeboDispatchRequest $request)
    {
        $dispatch = new CeboDispatch();
        $dispatch->supplier_id = $request->input('supplier.id');
        $dispatch->date = $request->date;

        if ($request->has('notes')) {
            $dispatch->notes = $request->notes;
        }

        $dispatch->save();

        if ($request->has('details')) {
            foreach ($request->details as $detail) {
                $dispatch->products()->create([
                    'product_id' => $detail['product']['id'],
                    'net_weight' => $detail['netWeight']
                ]);
            }
        }

        return response()->json([
            'message' => 'Despacho de cebo creado correctamente.',
            'data' => new CeboDispatchResource($dispatch),
        ], 201);
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
        
        DB::transaction(function () use ($dispatch, $validated) {
            $dispatch->update([
                'supplier_id' => $validated['supplier']['id'],
                'date' => $validated['date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $dispatch->products()->delete();
            foreach ($validated['details'] as $detail) {
                $dispatch->products()->create([
                    'product_id' => $detail['product']['id'],
                    'net_weight' => $detail['netWeight']
                ]);
            }
        });

        $dispatch->refresh();
        return response()->json([
            'message' => 'Despacho de cebo actualizado correctamente.',
            'data' => new CeboDispatchResource($dispatch),
        ]);
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

        CeboDispatch::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Despachos de cebo eliminados correctamente']);
    }
}
