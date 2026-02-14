<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexProductionOutputConsumptionRequest;
use App\Http\Requests\v2\StoreMultipleProductionOutputConsumptionsRequest;
use App\Http\Requests\v2\StoreProductionOutputConsumptionRequest;
use App\Http\Requests\v2\UpdateProductionOutputConsumptionRequest;
use App\Http\Resources\v2\ProductionOutputConsumptionResource;
use App\Models\ProductionOutputConsumption;
use App\Models\ProductionRecord;
use App\Services\Production\ProductionOutputConsumptionService;
use Illuminate\Http\Request;

class ProductionOutputConsumptionController extends Controller
{
    public function __construct(
        private ProductionOutputConsumptionService $productionOutputConsumptionService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(IndexProductionOutputConsumptionRequest $request)
    {
        $query = ProductionOutputConsumption::query();
        $query->with(['productionRecord.process', 'productionOutput.product']);

        if ($request->filled('production_record_id')) {
            $query->where('production_record_id', $request->production_record_id);
        }
        if ($request->filled('production_output_id')) {
            $query->where('production_output_id', $request->production_output_id);
        }
        if ($request->filled('production_id')) {
            $query->whereHas('productionRecord', fn ($q) => $q->where('production_id', $request->production_id));
        }
        if ($request->filled('parent_record_id')) {
            $query->whereHas('productionRecord', fn ($q) => $q->where('parent_record_id', $request->parent_record_id));
        }

        $perPage = $request->input('perPage', 15);
        return ProductionOutputConsumptionResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductionOutputConsumptionRequest $request)
    {
        try {
            $consumption = $this->productionOutputConsumptionService->create($request->validated());

            return response()->json([
                'message' => 'Consumo de output creado correctamente.',
                'data' => new ProductionOutputConsumptionResource($consumption),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $consumption = ProductionOutputConsumption::with(['productionRecord.process', 'productionOutput.product'])
            ->findOrFail($id);
        $this->authorize('view', $consumption);

        return response()->json([
            'message' => 'Consumo de output obtenido correctamente.',
            'data' => new ProductionOutputConsumptionResource($consumption),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductionOutputConsumptionRequest $request, string $id)
    {
        $consumption = ProductionOutputConsumption::findOrFail($id);
        $this->authorize('update', $consumption);
        try {
            $consumption = $this->productionOutputConsumptionService->update($consumption, $request->validated());

            return response()->json([
                'message' => 'Consumo de output actualizado correctamente.',
                'data' => new ProductionOutputConsumptionResource($consumption),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $consumption = ProductionOutputConsumption::findOrFail($id);
        $this->authorize('delete', $consumption);
        $this->productionOutputConsumptionService->delete($consumption);

        return response()->json([
            'message' => 'Consumo de output eliminado correctamente.',
        ], 200);
    }

    /**
     * Store multiple consumptions at once
     */
    public function storeMultiple(StoreMultipleProductionOutputConsumptionsRequest $request)
    {
        try {
            $result = $this->productionOutputConsumptionService->createMultiple(
                $request->validated()['production_record_id'],
                $request->validated()['consumptions']
            );

            return response()->json([
                'message' => count($result['created']) . ' consumo(s) creado(s) correctamente.',
                'data' => ProductionOutputConsumptionResource::collection($result['created']),
                'errors' => $result['errors'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear los consumos.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtener outputs disponibles para consumo por un proceso hijo
     */
    public function getAvailableOutputs(string $productionRecordId)
    {
        $record = ProductionRecord::findOrFail($productionRecordId);
        $this->authorize('view', $record);
        try {
            $availableOutputs = $this->productionOutputConsumptionService->getAvailableOutputs($productionRecordId);

            return response()->json([
                'message' => 'Outputs disponibles obtenidos correctamente.',
                'data' => collect($availableOutputs)->map(function ($item) {
                    return [
                        'output' => new \App\Http\Resources\v2\ProductionOutputResource($item['output']),
                        'totalWeight' => $item['totalWeight'],
                        'totalBoxes' => $item['totalBoxes'],
                        'consumedWeight' => $item['consumedWeight'],
                        'consumedBoxes' => $item['consumedBoxes'],
                        'availableWeight' => $item['availableWeight'],
                        'availableBoxes' => $item['availableBoxes'],
                        'hasExistingConsumption' => $item['hasExistingConsumption'],
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'data' => [],
            ], 422);
        }
    }
}

