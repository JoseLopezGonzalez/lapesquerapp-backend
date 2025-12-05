<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\StoreProductionRequest;
use App\Http\Requests\v2\UpdateProductionRequest;
use App\Http\Resources\v2\ProductionResource;
use App\Models\Production;
use App\Services\Production\ProductionService;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function __construct(
        private ProductionService $productionService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['lot', 'species_id', 'status']);
        $perPage = $request->input('perPage', 15);

        $productions = $this->productionService->list($filters, $perPage);

        return ProductionResource::collection($productions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductionRequest $request)
    {
        $production = $this->productionService->create($request->validated());

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
        $result = $this->productionService->getWithReconciliation($id);
        $production = $result['production'];
        $reconciliation = $result['reconciliation'];

        return response()->json([
            'message' => 'Producción obtenida correctamente.',
            'data' => [
                ...(new ProductionResource($production))->toArray(request()),
                'reconciliation' => $reconciliation,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductionRequest $request, string $id)
    {
        $production = Production::findOrFail($id);
        $production = $this->productionService->update($production, $request->validated());

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
        $this->productionService->delete($production);

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

    /**
     * Obtener productos disponibles con ese lote para facilitar creación de outputs
     * ✨ Devuelve productos con sus totales (cajas y peso) desde stock, ventas y reprocesados
     * 
     * Este endpoint facilita al frontend la creación de outputs basándose en
     * los productos que realmente existen en el sistema con ese lote.
     */
    public function getAvailableProductsForOutputs(string $id)
    {
        $production = Production::findOrFail($id);

        $products = $production->getAvailableProductsForOutputs();

        return response()->json([
            'message' => 'Productos disponibles obtenidos correctamente.',
            'data' => $products,
        ]);
    }
}
