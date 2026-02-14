<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexCostCatalogRequest;
use App\Http\Requests\v2\StoreCostCatalogRequest;
use App\Http\Requests\v2\UpdateCostCatalogRequest;
use App\Models\CostCatalog;
use Illuminate\Http\JsonResponse;

class CostCatalogController extends Controller
{
    public function index(IndexCostCatalogRequest $request): JsonResponse
    {
        $query = CostCatalog::query();

        if ($request->filled('cost_type')) {
            $query->ofType($request->cost_type);
        }
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

    public function store(StoreCostCatalogRequest $request): JsonResponse
    {
        $cost = CostCatalog::create($request->validated());

        return response()->json([
            'message' => 'Coste agregado al catálogo correctamente.',
            'data' => $cost,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $cost = CostCatalog::findOrFail($id);
        $this->authorize('view', $cost);

        return response()->json([
            'message' => 'Coste del catálogo obtenido correctamente.',
            'data' => $cost,
        ]);
    }

    public function update(UpdateCostCatalogRequest $request, string $id): JsonResponse
    {
        $cost = CostCatalog::findOrFail($id);
        $this->authorize('update', $cost);
        $cost->update($request->validated());

        return response()->json([
            'message' => 'Coste del catálogo actualizado correctamente.',
            'data' => $cost,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $cost = CostCatalog::findOrFail($id);
        $this->authorize('delete', $cost);

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
