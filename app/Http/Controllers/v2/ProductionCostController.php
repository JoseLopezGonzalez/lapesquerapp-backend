<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\ProductionCost;
use App\Models\ProductionRecord;
use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ProductionCostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductionCost::query();

        // Filtrar por proceso
        if ($request->has('production_record_id')) {
            $query->where('production_record_id', $request->production_record_id);
        }

        // Filtrar por producción (lote)
        if ($request->has('production_id')) {
            $query->where('production_id', $request->production_id);
        }

        // Filtrar por tipo de coste
        if ($request->has('cost_type')) {
            $query->where('cost_type', $request->cost_type);
        }

        // Cargar relaciones
        $query->with(['costCatalog', 'productionRecord', 'production']);

        $perPage = $request->input('perPage', 15);
        $costs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Costes obtenidos correctamente.',
            'data' => $costs,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'production_record_id' => 'nullable|exists:production_records,id',
            'production_id' => 'nullable|exists:productions,id',
            'cost_catalog_id' => 'nullable|exists:cost_catalog,id',
            'cost_type' => [
                'required_without:cost_catalog_id',
                Rule::in([
                    ProductionCost::COST_TYPE_PRODUCTION,
                    ProductionCost::COST_TYPE_LABOR,
                    ProductionCost::COST_TYPE_OPERATIONAL,
                    ProductionCost::COST_TYPE_PACKAGING,
                ]),
            ],
            'name' => 'required_without:cost_catalog_id|nullable|string|max:255',
            'description' => 'nullable|string',
            'total_cost' => 'nullable|numeric|min:0',
            'cost_per_kg' => 'nullable|numeric|min:0',
            'distribution_unit' => 'nullable|string',
            'cost_date' => 'nullable|date',
        ]);

        // Validar que solo uno de production_record_id o production_id esté presente
        if (empty($validated['production_record_id']) && empty($validated['production_id'])) {
            return response()->json([
                'message' => 'Debe especificarse production_record_id o production_id.',
            ], 422);
        }

        if (!empty($validated['production_record_id']) && !empty($validated['production_id'])) {
            return response()->json([
                'message' => 'Solo uno de production_record_id o production_id debe estar presente.',
            ], 422);
        }

        // Validar que solo uno de total_cost o cost_per_kg esté presente
        if (empty($validated['total_cost']) && empty($validated['cost_per_kg'])) {
            return response()->json([
                'message' => 'Se debe especificar O bien total_cost O bien cost_per_kg.',
            ], 422);
        }

        if (!empty($validated['total_cost']) && !empty($validated['cost_per_kg'])) {
            return response()->json([
                'message' => 'Solo uno de total_cost o cost_per_kg debe estar presente.',
            ], 422);
        }

        $cost = ProductionCost::create($validated);

        return response()->json([
            'message' => 'Coste creado correctamente.',
            'data' => $cost->load(['costCatalog', 'productionRecord', 'production']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $cost = ProductionCost::with(['costCatalog', 'productionRecord', 'production'])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Coste obtenido correctamente.',
            'data' => $cost,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $cost = ProductionCost::findOrFail($id);

        $validated = $request->validate([
            'cost_catalog_id' => 'nullable|exists:cost_catalog,id',
            'cost_type' => [
                'sometimes',
                Rule::in([
                    ProductionCost::COST_TYPE_PRODUCTION,
                    ProductionCost::COST_TYPE_LABOR,
                    ProductionCost::COST_TYPE_OPERATIONAL,
                    ProductionCost::COST_TYPE_PACKAGING,
                ]),
            ],
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'total_cost' => 'nullable|numeric|min:0',
            'cost_per_kg' => 'nullable|numeric|min:0',
            'distribution_unit' => 'nullable|string',
            'cost_date' => 'nullable|date',
        ]);

        // Validar que solo uno de total_cost o cost_per_kg esté presente
        if (isset($validated['total_cost']) && isset($validated['cost_per_kg'])) {
            if ($validated['total_cost'] !== null && $validated['cost_per_kg'] !== null) {
                return response()->json([
                    'message' => 'Solo uno de total_cost o cost_per_kg debe estar presente.',
                ], 422);
            }
        }

        $cost->update($validated);

        return response()->json([
            'message' => 'Coste actualizado correctamente.',
            'data' => $cost->load(['costCatalog', 'productionRecord', 'production']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $cost = ProductionCost::findOrFail($id);
        $cost->delete();

        return response()->json([
            'message' => 'Coste eliminado correctamente.',
        ], 200);
    }
}
