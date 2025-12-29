<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\CostCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CostCatalogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CostCatalog::query();

        // Filtrar por tipo de coste
        if ($request->has('cost_type')) {
            $query->ofType($request->cost_type);
        }

        // Filtrar solo activos
        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $perPage = $request->input('perPage', 15);
        $costs = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'message' => 'Catálogo de costes obtenido correctamente.',
            'data' => $costs,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:cost_catalog,name',
            'cost_type' => [
                'required',
                Rule::in([
                    CostCatalog::COST_TYPE_PRODUCTION,
                    CostCatalog::COST_TYPE_LABOR,
                    CostCatalog::COST_TYPE_OPERATIONAL,
                    CostCatalog::COST_TYPE_PACKAGING,
                ]),
            ],
            'description' => 'nullable|string',
            'default_unit' => [
                'nullable',
                Rule::in([CostCatalog::DEFAULT_UNIT_TOTAL, CostCatalog::DEFAULT_UNIT_PER_KG]),
            ],
            'is_active' => 'boolean',
        ]);

        $cost = CostCatalog::create($validated);

        return response()->json([
            'message' => 'Coste agregado al catálogo correctamente.',
            'data' => $cost,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $cost = CostCatalog::findOrFail($id);

        return response()->json([
            'message' => 'Coste del catálogo obtenido correctamente.',
            'data' => $cost,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $cost = CostCatalog::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('cost_catalog', 'name')->ignore($cost->id),
            ],
            'cost_type' => [
                'sometimes',
                Rule::in([
                    CostCatalog::COST_TYPE_PRODUCTION,
                    CostCatalog::COST_TYPE_LABOR,
                    CostCatalog::COST_TYPE_OPERATIONAL,
                    CostCatalog::COST_TYPE_PACKAGING,
                ]),
            ],
            'description' => 'nullable|string',
            'default_unit' => [
                'nullable',
                Rule::in([CostCatalog::DEFAULT_UNIT_TOTAL, CostCatalog::DEFAULT_UNIT_PER_KG]),
            ],
            'is_active' => 'boolean',
        ]);

        $cost->update($validated);

        return response()->json([
            'message' => 'Coste del catálogo actualizado correctamente.',
            'data' => $cost,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $cost = CostCatalog::findOrFail($id);

        // Verificar si está siendo usado
        if ($cost->productionCosts()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el coste porque está siendo usado en producciones.',
            ], 422);
        }

        $cost->delete();

        return response()->json([
            'message' => 'Coste eliminado del catálogo correctamente.',
        ], 200);
    }
}
