<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\v1\RawMaterialReceptionResource;
use App\Models\RawMaterial;
use App\Models\RawMaterialReception;
use App\Models\RawMaterialReceptionProduct;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class RawMaterialReceptionController extends Controller
{
    public function index(Request $request)
    {
        $query = RawMaterialReception::query();
        $query->with('supplier', 'products.product');

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('suppliers')) {
            $query->whereIn('supplier_id', $request->suppliers);
        }

        if ($request->has('dates')) {
            $query->whereBetween('date', [$request->dates['start'], $request->dates['end']]);
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

        /* Order by Date Descen */
        $query->orderBy('date', 'desc');

        $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
        return RawMaterialReceptionResource::collection($query->paginate($perPage));
    }

    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'supplier.id' => 'required',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'details' => 'required|array',
            'details.*.product.id' => 'required|exists:products,id',
            'details.*.netWeight' => 'required|numeric',
            'details.*.price' => 'nullable|numeric|min:0',
            'declaredTotalAmount' => 'nullable|numeric|min:0',
            'declaredTotalNetWeight' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Código de estado 422 - Unprocessable Entity
        }

        $reception = new RawMaterialReception();
        $reception->supplier_id = $request->supplier['id'];
        $reception->date = $request->date;

        if ($request->has('declaredTotalAmount')) {
            $reception->declared_total_amount = $request->declaredTotalAmount;
        }

        if ($request->has('declaredTotalNetWeight')) {
            $reception->declared_total_net_weight = $request->declaredTotalNetWeight;
        }

        if ($request->has('notes')) {
            $reception->notes = $request->notes;
        }

        $reception->save();

        if ($request->has('details')) {
            /* foreach ($request->details as $detail) {
                $reception->products()->create([
                    'product_id' => $detail['product']['id'],
                    'net_weight' => $detail['netWeight'],
                    'price' => $detail['price'] ?? null
                ]);
            } */
            if ($request->has('details')) {
                foreach ($request->details as $detail) {
                    $productId = $detail['product']['id'];
                    $netWeight = $detail['netWeight'];
                    $price = $detail['price'] ?? null;

                    // Si no viene el precio, lo buscamos
                    if (is_null($price)) {
                        $price = RawMaterialReceptionProduct::where('product_id', $productId)
                            ->whereHas('reception', function ($query) use ($request) {
                                $query->where('supplier_id', $request->supplier['id']);
                            })
                            ->latest('created_at')
                            ->value('price');
                    }

                    $reception->products()->create([
                        'product_id' => $productId,
                        'net_weight' => $netWeight,
                        'price' => $price,
                    ]);
                }
            }

        }

        $reception->save();


        return new RawMaterialReceptionResource($reception);
    }

    public function show($id)
    {
        $reception = RawMaterialReception::with('supplier', 'products.product')->findOrFail($id);
        return new RawMaterialReceptionResource($reception);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'supplier.id' => 'required',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'details' => 'required|array',
            'details.*.product.id' => 'required|exists:products,id',
            'details.*.netWeight' => 'required|numeric',
            'details.*.price' => 'nullable|numeric|min:0',
            'declaredTotalAmount' => 'nullable|numeric|min:0',
            'declaredTotalNetWeight' => 'nullable|numeric|min:0'
        ]);

        $reception = RawMaterialReception::findOrFail($id);
        $reception->update([
            'supplier_id' => $validated['supplier']['id'],
            'date' => $validated['date'],
            'notes' => $validated['notes'],
            'declared_total_amount' => $request->declaredTotalAmount ?? null,
            'declared_total_net_weight' => $request->declaredTotalNetWeight ?? null,
        ]);

        $reception->products()->delete();
        /* foreach ($validated['details'] as $detail) {
            $reception->products()->create([
                'product_id' => $detail['product']['id'],
                'net_weight' => $detail['netWeight'],
                'price' => $detail['price'] ?? null
            ]);
        } */
        foreach ($validated['details'] as $detail) {
            $productId = $detail['product']['id'];
            $netWeight = $detail['netWeight'];
            $price = $detail['price'] ?? null;

            if (is_null($price)) {
                $price = RawMaterialReceptionProduct::where('product_id', $productId)
                    ->whereHas('reception', function ($query) use ($validated) {
                        $query->where('supplier_id', $validated['supplier']['id']);
                    })
                    ->latest('created_at')
                    ->value('price');
            }

            $reception->products()->create([
                'product_id' => $productId,
                'net_weight' => $netWeight,
                'price' => $price,
            ]);
        }

        return new RawMaterialReceptionResource($reception);



    }

    public function destroy($id)
    {

        $order = RawMaterialReception::findOrFail($id);
        $order->delete();
        return response()->json(['message' => 'Palet eliminado correctamente'], 200);
    }

    public function updateDeclaredData(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:tenant.suppliers,id',
            'date' => 'required|date',
            'declared_total_amount' => 'nullable|numeric|min:0',
            'declared_total_net_weight' => 'nullable|numeric|min:0',
        ]);

        // Buscar la recepción
        $reception = RawMaterialReception::where('supplier_id', $validated['supplier_id'])
            ->whereDate('date', $validated['date'])
            ->first();

        if (!$reception) {
            return response()->json(['error' => 'Reception not found'], 404);
        }

        // Actualizar los valores
        $reception->update([
            'declared_total_amount' => $validated['declared_total_amount'],
            'declared_total_net_weight' => $validated['declared_total_net_weight'],
        ]);

        return new RawMaterialReceptionResource($reception);
    }

}
