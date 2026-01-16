<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\CeboDispatchResource;
use App\Models\CeboDispatch;
use App\Models\CeboDispatchProduct;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CeboDispatchController extends Controller
{
    public function index(Request $request)
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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier.id' => 'required',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'details' => 'required|array',
            'details.*.product.id' => 'required|exists:tenant.products,id',
            'details.*.netWeight' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Código de estado 422 - Unprocessable Entity
        }

        $dispatch = new CeboDispatch();
        $dispatch->supplier_id = $request->supplier['id'];
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

        return new CeboDispatchResource($dispatch);
    }

    public function show($id)
    {
        $dispatch = CeboDispatch::with('supplier', 'products.product')->findOrFail($id);
        return new CeboDispatchResource($dispatch);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'supplier.id' => 'required',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'details' => 'required|array',
            'details.*.product.id' => 'required|exists:tenant.products,id',
            'details.*.netWeight' => 'required|numeric',
        ]);

        $dispatch = CeboDispatch::findOrFail($id);
        
        DB::transaction(function () use ($dispatch, $validated) {
            $dispatch->update([
                'supplier_id' => $validated['supplier']['id'],
                'date' => $validated['date'],
                'notes' => $validated['notes']
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
        return new CeboDispatchResource($dispatch);
    }

    public function destroy($id)
    {
        $dispatch = CeboDispatch::findOrFail($id);

        // Validar si el despacho tiene productos antes de eliminar
        if ($dispatch->products()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el despacho porque tiene productos asociados',
                'details' => 'El despacho contiene productos que deben eliminarse primero'
            ], 400);
        }

        $dispatch->delete();
        return response()->json(['message' => 'Despacho de cebo eliminado correctamente'], 200);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'No se proporcionaron IDs válidos'], 400);
        }

        // Validar que ningún despacho tenga productos
        $dispatchesWithProducts = CeboDispatch::whereIn('id', $ids)
            ->whereHas('products')
            ->pluck('id')
            ->toArray();

        if (!empty($dispatchesWithProducts)) {
            return response()->json([
                'message' => 'No se pueden eliminar algunos despachos porque tienen productos asociados',
                'details' => 'Los despachos con IDs: ' . implode(', ', $dispatchesWithProducts) . ' tienen productos asociados'
            ], 400);
        }

        CeboDispatch::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Despachos de cebo eliminados correctamente']);
    }
}
