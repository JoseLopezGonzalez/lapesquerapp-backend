<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProductionOutputResource;
use App\Models\ProductionOutput;
use Illuminate\Http\Request;

class ProductionOutputController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProductionOutput::query();

        // Cargar relaciones
        $query->with(['productionRecord', 'product']);

        // Filtro por production_record_id
        if ($request->has('production_record_id')) {
            $query->where('production_record_id', $request->production_record_id);
        }

        // Filtro por product_id
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filtro por lot_id
        if ($request->has('lot_id')) {
            $query->where('lot_id', $request->lot_id);
        }

        // Filtro por production_id (a través de production_record)
        if ($request->has('production_id')) {
            $query->whereHas('productionRecord', function ($q) use ($request) {
                $q->where('production_id', $request->production_id);
            });
        }

        $perPage = $request->input('perPage', 15);
        return ProductionOutputResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'production_record_id' => 'required|exists:tenant.production_records,id',
            'product_id' => 'required|exists:tenant.products,id',
            'lot_id' => 'nullable|string',
            'boxes' => 'required|integer|min:0',
            'weight_kg' => 'required|numeric|min:0',
        ]);

        $output = ProductionOutput::create($validated);

        // Cargar relaciones para la respuesta
        $output->load(['productionRecord', 'product']);

        return response()->json([
            'message' => 'Salida de producción creada correctamente.',
            'data' => new ProductionOutputResource($output),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $output = ProductionOutput::with(['productionRecord', 'product'])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Salida de producción obtenida correctamente.',
            'data' => new ProductionOutputResource($output),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $output = ProductionOutput::findOrFail($id);

        $validated = $request->validate([
            'production_record_id' => 'sometimes|exists:tenant.production_records,id',
            'product_id' => 'sometimes|exists:tenant.products,id',
            'lot_id' => 'sometimes|nullable|string',
            'boxes' => 'sometimes|integer|min:0',
            'weight_kg' => 'sometimes|numeric|min:0',
        ]);

        $output->update($validated);

        // Cargar relaciones para la respuesta
        $output->load(['productionRecord', 'product']);

        return response()->json([
            'message' => 'Salida de producción actualizada correctamente.',
            'data' => new ProductionOutputResource($output),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $output = ProductionOutput::findOrFail($id);
        $output->delete();

        return response()->json([
            'message' => 'Salida de producción eliminada correctamente.',
        ], 200);
    }
}
