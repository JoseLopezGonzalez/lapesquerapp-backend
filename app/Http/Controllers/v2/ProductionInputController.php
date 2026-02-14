<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleProductionInputsRequest;
use App\Http\Requests\v2\IndexProductionInputRequest;
use App\Http\Requests\v2\StoreMultipleProductionInputsRequest;
use App\Http\Requests\v2\StoreProductionInputRequest;
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
    public function index(IndexProductionInputRequest $request)
    {
        $query = ProductionInput::query();
        $query->with(['productionRecord', 'box.product']);

        if ($request->filled('production_record_id')) {
            $query->where('production_record_id', $request->production_record_id);
        }
        if ($request->filled('box_id')) {
            $query->where('box_id', $request->box_id);
        }
        if ($request->filled('production_id')) {
            $query->whereHas('productionRecord', fn ($q) => $q->where('production_id', $request->production_id));
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
            // Dejar que el Handler procese la excepción para formato consistente con userMessage
            throw $e;
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
            // Dejar que el Handler procese la excepción para formato consistente con userMessage
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $input = ProductionInput::with(['productionRecord', 'box.product'])
            ->findOrFail($id);
        $this->authorize('view', $input);

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
        $this->authorize('delete', $input);
        $this->productionInputService->delete($input);

        return response()->json([
            'message' => 'Entrada de producción eliminada correctamente.',
        ], 200);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function destroyMultiple(DestroyMultipleProductionInputsRequest $request)
    {
        $ids = $request->validated('ids');
        $inputs = ProductionInput::whereIn('id', $ids)->get();

        foreach ($inputs as $input) {
            $this->authorize('delete', $input);
        }

        $deletedCount = $this->productionInputService->deleteMultiple($ids);

        return response()->json([
            'message' => "{$deletedCount} entrada(s) de producción eliminada(s) correctamente.",
        ], 200);
    }
}
