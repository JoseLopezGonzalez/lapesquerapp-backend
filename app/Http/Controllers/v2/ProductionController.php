<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\CloseProductionRequest;
use App\Http\Requests\v2\DestroyMultipleProductionsRequest;
use App\Http\Requests\v2\IndexProductionRequest;
use App\Http\Requests\v2\ReopenProductionRequest;
use App\Http\Requests\v2\StoreProductionRequest;
use App\Http\Requests\v2\UpdateProductionRequest;
use App\Http\Resources\v2\ProductionResource;
use App\Models\Production;
use App\Services\Production\ProductionClosureService;
use App\Services\Production\ProductionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductionController extends Controller
{
    public function __construct(
        private ProductionService $productionService,
        private ProductionClosureService $closureService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexProductionRequest $request)
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
        $this->authorize('view', $production);
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
        $this->authorize('update', $production);
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
        $this->authorize('delete', $production);
        $this->productionService->delete($production);

        return response()->json([
            'message' => 'Producción eliminada correctamente.',
        ], 200);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function destroyMultiple(DestroyMultipleProductionsRequest $request)
    {
        $ids = $request->validated('ids');
        $productions = Production::whereIn('id', $ids)->get();

        foreach ($productions as $production) {
            $this->authorize('delete', $production);
        }

        $deletedCount = $this->productionService->deleteMultiple($ids);

        return response()->json([
            'message' => "{$deletedCount} producción(es) eliminada(s) correctamente.",
        ], 200);
    }

    /**
     * Obtener el diagrama calculado dinámicamente para una producción
     */
    public function getDiagram(string $id)
    {
        $production = Production::findOrFail($id);
        $this->authorize('view', $production);

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
    public function getProcessTree(Request $request, string $id)
    {
        $validated = $request->validate([
            'customerId' => ['nullable', 'integer', 'exists:customers,id'],
            'orderId' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        if (isset($validated['customerId'], $validated['orderId'])) {
            throw ValidationException::withMessages([
                'customerId' => ['El filtro customerId no puede combinarse con orderId.'],
                'orderId' => ['El filtro orderId no puede combinarse con customerId.'],
            ]);
        }

        $production = Production::findOrFail($id);
        $this->authorize('view', $production);

        if (isset($validated['customerId']) || isset($validated['orderId'])) {
            $filteredTree = $production->buildFilteredProcessTree(
                isset($validated['customerId']) ? (int) $validated['customerId'] : null,
                isset($validated['orderId']) ? (int) $validated['orderId'] : null,
            );

            return response()->json([
                'message' => 'Árbol de procesos obtenido correctamente.',
                'data' => $filteredTree,
            ]);
        }

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
        $this->authorize('view', $production);

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
        $this->authorize('view', $production);

        $reconciliation = $production->reconcile();

        return response()->json([
            'message' => 'Conciliación obtenida correctamente.',
            'data' => $reconciliation,
        ]);
    }

    /**
     * Evalúa si una producción puede cerrarse definitivamente.
     */
    public function closureCheck(string $id)
    {
        $production = Production::findOrFail($id);
        $this->authorize('view', $production);

        $result = $this->closureService->canClose($production);

        return response()->json([
            'message' => 'Evaluación de cierre obtenida correctamente.',
            'data' => $result,
        ]);
    }

    /**
     * Cierra definitivamente una producción.
     */
    public function close(CloseProductionRequest $request, string $id)
    {
        $production = Production::findOrFail($id);
        $this->authorize('close', $production);

        $production = $this->closureService->close(
            $production,
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'message' => 'Producción cerrada definitivamente.',
            'data' => new ProductionResource($production),
        ]);
    }

    /**
     * Reabre una producción cerrada (solo roles autorizados).
     */
    public function reopen(ReopenProductionRequest $request, string $id)
    {
        $production = Production::findOrFail($id);
        $this->authorize('reopen', $production);

        $production = $this->closureService->reopen(
            $production,
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'message' => 'Producción reabierta correctamente.',
            'data' => new ProductionResource($production),
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
        $this->authorize('view', $production);

        $products = $production->getAvailableProductsForOutputs();

        return response()->json([
            'message' => 'Productos disponibles obtenidos correctamente.',
            'data' => $products,
        ]);
    }
}
