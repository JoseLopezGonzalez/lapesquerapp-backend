<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\StoreProductionOutputRequest;
use App\Http\Requests\v2\UpdateProductionOutputRequest;
use App\Http\Requests\v2\StoreMultipleProductionOutputsRequest;
use App\Http\Resources\v2\ProductionOutputResource;
use App\Models\ProductionOutput;
use App\Services\Production\ProductionOutputService;
use Illuminate\Http\Request;

class ProductionOutputController extends Controller
{
    public function __construct(
        private ProductionOutputService $productionOutputService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProductionOutput::query();

        // Cargar relaciones
        $query->with(['productionRecord', 'product', 'sources']);

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
    public function store(StoreProductionOutputRequest $request)
    {
        $output = $this->productionOutputService->create($request->validated());

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
        $output = ProductionOutput::with(['productionRecord', 'product', 'sources'])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Salida de producción obtenida correctamente.',
            'data' => new ProductionOutputResource($output),
        ]);
    }

    /**
     * Obtener desglose de costes de un output
     */
    public function getCostBreakdown(string $id)
    {
        $output = ProductionOutput::with(['productionRecord', 'product', 'sources'])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Desglose de costes obtenido correctamente.',
            'data' => [
                'output' => new ProductionOutputResource($output),
                'cost_breakdown' => $output->cost_breakdown,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductionOutputRequest $request, string $id)
    {
        $output = ProductionOutput::findOrFail($id);
        $output = $this->productionOutputService->update($output, $request->validated());

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
        $this->productionOutputService->delete($output);

        return response()->json([
            'message' => 'Salida de producción eliminada correctamente.',
        ], 200);
    }

    /**
     * Store multiple outputs at once
     */
    public function storeMultiple(StoreMultipleProductionOutputsRequest $request)
    {
        try {
            $result = $this->productionOutputService->createMultiple(
                $request->validated()['production_record_id'],
                $request->validated()['outputs']
            );

            return response()->json([
                'message' => count($result['created']) . ' salida(s) creada(s) correctamente.',
                'data' => ProductionOutputResource::collection($result['created']),
                'errors' => $result['errors'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear las salidas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
