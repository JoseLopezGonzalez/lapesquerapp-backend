<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProductionResource;
use App\Models\Production;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Production::query();

        // Cargar relaciones
        $query->with(['species', 'captureZone', 'records']);

        // Filtro por lot
        if ($request->has('lot')) {
            $query->where('lot', 'like', "%{$request->lot}%");
        }

        // Filtro por species_id
        if ($request->has('species_id')) {
            $query->where('species_id', $request->species_id);
        }

        // Filtro por estado (abierto/cerrado)
        if ($request->has('status')) {
            if ($request->status === 'open') {
                $query->whereNotNull('opened_at')->whereNull('closed_at');
            } elseif ($request->status === 'closed') {
                $query->whereNotNull('closed_at');
            }
        }

        // Ordenar por opened_at descendente
        $query->orderBy('opened_at', 'desc');

        $perPage = $request->input('perPage', 15);
        return ProductionResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lot' => 'nullable|string',
            'species_id' => 'nullable|exists:tenant.species,id',
            'capture_zone_id' => 'nullable|exists:tenant.capture_zones,id',
            'notes' => 'nullable|string',
        ]);

        $production = Production::create($validated);
        
        // Abrir el lote automáticamente
        $production->open();

        // Cargar relaciones para la respuesta
        $production->load(['species', 'captureZone', 'records.process']);

        return response()->json([
            'message' => 'Producción creada correctamente.',
            'data' => new ProductionResource($production),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $production = Production::with(['species', 'captureZone', 'records.process'])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Producción obtenida correctamente.',
            'data' => new ProductionResource($production),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $production = Production::findOrFail($id);

        $validated = $request->validate([
            'lot' => 'sometimes|nullable|string',
            'species_id' => 'sometimes|nullable|exists:tenant.species,id',
            'capture_zone_id' => 'sometimes|nullable|exists:tenant.capture_zones,id',
            'notes' => 'sometimes|nullable|string',
        ]);

        $production->update($validated);

        // Cargar relaciones para la respuesta
        $production->load(['species', 'captureZone', 'records.process']);

        return response()->json([
            'message' => 'Producción actualizada correctamente.',
            'data' => new ProductionResource($production),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $production = Production::findOrFail($id);
        $production->delete();

        return response()->json([
            'message' => 'Producción eliminada correctamente.',
        ], 200);
    }

    /**
     * Obtener el diagrama calculado dinámicamente para una producción
     */
    public function getDiagram(string $id)
    {
        $production = Production::findOrFail($id);

        $diagramData = $production->getDiagramData();

        return response()->json([
            'message' => 'Diagrama obtenido correctamente.',
            'data' => $diagramData,
        ]);
    }

    /**
     * Obtener el árbol completo de procesos de una producción
     * Incluye nodos de venta y stock como hijos de nodos finales
     */
    public function getProcessTree(string $id)
    {
        $production = Production::findOrFail($id);

        $tree = $production->buildProcessTree();

        // Convertir a estructura del diagrama
        $processNodes = $tree->map(function ($record) {
            return $record->getNodeData();
        })->toArray();

        // ✨ Añadir nodos de venta y stock como hijos de nodos finales
        $processNodes = $production->attachSalesAndStockNodes($processNodes);

        return response()->json([
            'message' => 'Árbol de procesos obtenido correctamente.',
            'data' => [
                'processNodes' => $processNodes,
                'totals' => $production->calculateGlobalTotals(),
            ],
        ]);
    }

    /**
     * Obtener totales globales de una producción
     */
    public function getTotals(string $id)
    {
        $production = Production::findOrFail($id);

        return response()->json([
            'message' => 'Totales obtenidos correctamente.',
            'data' => $production->calculateGlobalTotals(),
        ]);
    }

    /**
     * Obtener información de conciliación de una producción
     */
    public function getReconciliation(string $id)
    {
        $production = Production::findOrFail($id);

        $reconciliation = $production->reconcile();

        return response()->json([
            'message' => 'Conciliación obtenida correctamente.',
            'data' => $reconciliation,
        ]);
    }
}
