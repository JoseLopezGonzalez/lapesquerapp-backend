<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProductionOutputResource;
use App\Models\ProductionOutput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Store multiple outputs at once
     */
    public function storeMultiple(Request $request)
    {
        $validated = $request->validate([
            'production_record_id' => 'required|exists:tenant.production_records,id',
            'outputs' => 'required|array|min:1',
            'outputs.*.product_id' => 'required|exists:tenant.products,id',
            'outputs.*.lot_id' => 'nullable|string',
            'outputs.*.boxes' => 'required|integer|min:0',
            'outputs.*.weight_kg' => 'required|numeric|min:0',
        ]);

        $created = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($validated['outputs'] as $index => $outputData) {
                try {
                    $output = ProductionOutput::create([
                        'production_record_id' => $validated['production_record_id'],
                        'product_id' => $outputData['product_id'],
                        'lot_id' => $outputData['lot_id'] ?? null,
                        'boxes' => $outputData['boxes'],
                        'weight_kg' => $outputData['weight_kg'],
                    ]);

                    $output->load(['productionRecord', 'product']);
                    $created[] = new ProductionOutputResource($output);
                } catch (\Exception $e) {
                    $errors[] = "Error en la salida #{$index}: " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'message' => count($created) . ' salida(s) creada(s) correctamente.',
                'data' => $created,
                'errors' => $errors,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear las salidas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
