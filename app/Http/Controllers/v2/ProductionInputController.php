<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\StoreProductionInputRequest;
use App\Http\Requests\v2\StoreMultipleProductionInputsRequest;
use App\Http\Resources\v2\ProductionInputResource;
use App\Models\ProductionInput;
use App\Services\Production\ProductionInputService;
use Illuminate\Http\Request;

class ProductionInputController extends Controller
{
    public function __construct(
        private ProductionInputService $productionInputService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProductionInput::query();

        // Cargar relaciones
        $query->with(['productionRecord', 'box.product']);

        // Filtro por production_record_id
        if ($request->has('production_record_id')) {
            $query->where('production_record_id', $request->production_record_id);
        }

        // Filtro por box_id
        if ($request->has('box_id')) {
            $query->where('box_id', $request->box_id);
        }

        // Filtro por production_id (a través de production_record)
        if ($request->has('production_id')) {
            $query->whereHas('productionRecord', function ($q) use ($request) {
                $q->where('production_id', $request->production_id);
            });
        }

        return ProductionInputResource::collection($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductionInputRequest $request)
    {
        try {
            $input = $this->productionInputService->create($request->validated());

            return response()->json([
                'message' => 'Entrada de producción creada correctamente.',
                'data' => new ProductionInputResource($input),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Store multiple inputs at once
     */
    public function storeMultiple(StoreMultipleProductionInputsRequest $request)
    {
        try {
            $result = $this->productionInputService->createMultiple(
                $request->validated()['production_record_id'],
                $request->validated()['box_ids']
            );

            return response()->json([
                'message' => count($result['created']) . ' entradas creadas correctamente.',
                'data' => ProductionInputResource::collection($result['created']),
                'errors' => $result['errors'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear las entradas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $input = ProductionInput::with(['productionRecord', 'box.product'])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Entrada de producción obtenida correctamente.',
            'data' => new ProductionInputResource($input),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $input = ProductionInput::findOrFail($id);
        $this->productionInputService->delete($input);

        return response()->json([
            'message' => 'Entrada de producción eliminada correctamente.',
        ], 200);
    }
}
