<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexProductionCostRequest;
use App\Http\Requests\v2\StoreProductionCostRequest;
use App\Http\Requests\v2\UpdateProductionCostRequest;
use App\Models\ProductionCost;
use Illuminate\Http\JsonResponse;

use function normalizeDateToBusiness;

class ProductionCostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexProductionCostRequest $request): JsonResponse
    {
        $query = ProductionCost::query();
        $query->with(['costCatalog', 'productionRecord', 'production']);

        if ($request->filled('production_record_id')) {
            $query->where('production_record_id', $request->production_record_id);
        }
        if ($request->filled('production_id')) {
            $query->where('production_id', $request->production_id);
        }
        if ($request->filled('cost_type')) {
            $query->where('cost_type', $request->cost_type);
        }

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
    public function store(StoreProductionCostRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if (isset($validated['cost_date'])) {
            $validated['cost_date'] = normalizeDateToBusiness($validated['cost_date']);
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
        $this->authorize('view', $cost);

        return response()->json([
            'message' => 'Coste obtenido correctamente.',
            'data' => $cost,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductionCostRequest $request, string $id): JsonResponse
    {
        $cost = ProductionCost::findOrFail($id);
        $this->authorize('update', $cost);
        $validated = $request->validated();
        if (isset($validated['cost_date'])) {
            $validated['cost_date'] = normalizeDateToBusiness($validated['cost_date']);
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
        $this->authorize('delete', $cost);
        $cost->delete();

        return response()->json([
            'message' => 'Coste eliminado correctamente.',
        ], 200);
    }
}
