<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\StoreProductionRecordRequest;
use App\Http\Requests\v2\UpdateProductionRecordRequest;
use App\Http\Requests\v2\SyncProductionOutputsRequest;
use App\Http\Requests\v2\SyncProductionConsumptionsRequest;
use App\Http\Resources\v2\ProductionRecordResource;
use App\Http\Resources\v2\ProductionOutputResource;
use App\Models\ProductionRecord;
use App\Services\Production\ProductionRecordService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductionRecordController extends Controller
{
    public function __construct(
        private ProductionRecordService $productionRecordService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['production_id', 'root_only', 'parent_record_id', 'process_id', 'completed']);
        $perPage = $request->input('perPage', 15);

        $records = $this->productionRecordService->list($filters, $perPage);

        return ProductionRecordResource::collection($records);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductionRecordRequest $request)
    {
        $record = $this->productionRecordService->create($request->validated());

        return response()->json([
            'message' => 'Registro de producción creado correctamente.',
            'data' => new ProductionRecordResource($record),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $record = ProductionRecord::with([
            'production',
            'parent',
            'children',
            'process',
            'inputs.box.product',
            'outputs.product',
            'parentOutputConsumptions.productionOutput.product'
        ])->findOrFail($id);

        return response()->json([
            'message' => 'Registro de producción obtenido correctamente.',
            'data' => new ProductionRecordResource($record),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductionRecordRequest $request, string $id)
    {
        $record = ProductionRecord::findOrFail($id);
        $record = $this->productionRecordService->update($record, $request->validated());

        return response()->json([
            'message' => 'Registro de producción actualizado correctamente.',
            'data' => new ProductionRecordResource($record),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $record = ProductionRecord::findOrFail($id);
        $this->productionRecordService->delete($record);

        return response()->json([
            'message' => 'Registro de producción eliminado correctamente.',
        ], 200);
    }

    /**
     * Obtener el árbol completo de un registro (con hijos recursivos)
     */
    public function tree(string $id)
    {
        $record = ProductionRecord::with([
            'production',
            'parent',
            'process',
            'inputs.box.product',
            'outputs.product'
        ])->findOrFail($id);

        $record->buildTree();

        return response()->json([
            'message' => 'Árbol de procesos obtenido correctamente.',
            'data' => new ProductionRecordResource($record),
        ]);
    }

    /**
     * Finalizar un proceso
     */
    public function finish(string $id): JsonResponse
    {
        try {
            $record = ProductionRecord::findOrFail($id);
            $record = $this->productionRecordService->finish($record);

            return response()->json([
                'message' => 'Proceso finalizado correctamente.',
                'data' => new ProductionRecordResource($record),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Sincronizar todas las salidas de un proceso
     * Crea nuevas, actualiza existentes y elimina las que no están en el array
     */
    public function syncOutputs(SyncProductionOutputsRequest $request, string $id): JsonResponse
    {
        try {
            $record = ProductionRecord::findOrFail($id);
            $result = $this->productionRecordService->syncOutputs($record, $request->validated()['outputs']);

            return response()->json([
                'message' => 'Salidas sincronizadas correctamente.',
                'data' => new ProductionRecordResource($result['record']),
                'summary' => $result['summary'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al sincronizar las salidas.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Sincronizar todos los consumos de outputs del padre de un proceso
     * Crea nuevos, actualiza existentes y elimina los que no están en el array
     */
    public function syncConsumptions(SyncProductionConsumptionsRequest $request, string $id): JsonResponse
    {
        try {
            $record = ProductionRecord::findOrFail($id);
            $result = $this->productionRecordService->syncConsumptions($record, $request->validated()['consumptions']);

            return response()->json([
                'message' => 'Consumos sincronizados correctamente.',
                'data' => new ProductionRecordResource($result['record']),
                'summary' => $result['summary'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al sincronizar los consumos.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtener opciones para seleccionar nodos padres en el frontend
     */
    public function options(Request $request)
    {
        $filters = $request->only(['production_id', 'exclude_id']);
        $records = $this->productionRecordService->getOptions($filters);

        return response()->json([
            'message' => 'Opciones de nodos padres obtenidas correctamente.',
            'data' => $records->map(function ($record) {
                $processName = $record->process ? $record->process->name : 'Sin proceso';
                $productionLot = $record->production ? $record->production->lot : 'Sin lote';
                
                $label = $processName;
                if ($record->started_at) {
                    $label .= ' - ' . $record->started_at->format('d/m/Y H:i');
                }
                if ($record->isFinal()) {
                    $label .= ' (Final)';
                }
                if ($record->isRoot()) {
                    $label .= ' (Raíz)';
                }

                return [
                    'value' => $record->id,
                    'label' => $label,
                    'processName' => $processName,
                    'processId' => $record->process_id,
                    'productionId' => $record->production_id,
                    'productionLot' => $productionLot,
                    'isRoot' => $record->isRoot(),
                    'isFinal' => $record->isFinal(),
                    'isCompleted' => $record->isCompleted(),
                    'startedAt' => $record->started_at?->toIso8601String(),
                    'finishedAt' => $record->finished_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Obtener toda la información necesaria para crear sources de outputs
     * 
     * Este endpoint devuelve:
     * - Inputs de stock (cajas) disponibles con sus costes
     * - Consumos de outputs del padre disponibles con sus costes
     * - Información del proceso actual
     * - Totales y pesos disponibles
     * 
     * @param string $id ID del ProductionRecord
     */
    public function getSourcesData(string $id): JsonResponse
    {
        $record = ProductionRecord::with([
            'production',
            'process',
            'inputs.box.product',
            'inputs.box.pallet.reception',
            'parentOutputConsumptions.productionOutput.product',
            'parentOutputConsumptions.productionOutput.productionRecord.process',
        ])->findOrFail($id);

        // Preparar datos de inputs de stock (cajas)
        $stockBoxes = $record->inputs->map(function ($input) {
            $box = $input->box;
            if (!$box) {
                return null;
            }

            return [
                'productionInputId' => $input->id,
                'boxId' => $box->id,
                'product' => [
                    'id' => $box->product->id ?? null,
                    'name' => $box->product->name ?? null,
                ],
                'lot' => $box->lot,
                'netWeight' => (float) ($box->net_weight ?? 0),
                'grossWeight' => (float) ($box->gross_weight ?? 0),
                'costPerKg' => $box->cost_per_kg !== null ? (float) $box->cost_per_kg : null,
                'totalCost' => $box->total_cost !== null ? (float) $box->total_cost : null,
                'gs1128' => $box->gs1_128,
                'palletId' => $box->pallet_id,
            ];
        })->filter()->values();

        // Preparar datos de consumos de outputs del padre
        $parentOutputs = $record->parentOutputConsumptions->map(function ($consumption) {
            $output = $consumption->productionOutput;
            if (!$output) {
                return null;
            }

            $parentRecord = $output->productionRecord;
            $processName = $parentRecord && $parentRecord->process 
                ? $parentRecord->process->name 
                : 'Proceso padre';

            return [
                'productionOutputConsumptionId' => $consumption->id,
                'productionOutputId' => $output->id,
                'product' => [
                    'id' => $output->product->id ?? null,
                    'name' => $output->product->name ?? null,
                ],
                'lotId' => $output->lot_id,
                'consumedWeightKg' => (float) ($consumption->consumed_weight_kg ?? 0),
                'consumedBoxes' => (int) ($consumption->consumed_boxes ?? 0),
                'outputTotalWeight' => (float) ($output->weight_kg ?? 0),
                'outputTotalBoxes' => (int) ($output->boxes ?? 0),
                'outputAvailableWeight' => (float) ($output->available_weight_kg ?? 0),
                'outputAvailableBoxes' => (int) ($output->available_boxes ?? 0),
                'costPerKg' => $output->cost_per_kg !== null ? (float) $output->cost_per_kg : null,
                'totalCost' => $output->total_cost !== null ? (float) $output->total_cost : null,
                'parentProcess' => [
                    'id' => $parentRecord->id ?? null,
                    'name' => $processName,
                    'processId' => $parentRecord->process_id ?? null,
                ],
            ];
        })->filter()->values();

        // Calcular totales
        $totalStockWeight = $stockBoxes->sum('netWeight');
        $totalStockCost = $stockBoxes->sum(function ($box) {
            return $box['totalCost'] ?? 0;
        });
        $totalParentWeight = $parentOutputs->sum('consumedWeightKg');
        $totalParentCost = $parentOutputs->sum(function ($output) {
            return ($output['costPerKg'] ?? 0) * ($output['consumedWeightKg'] ?? 0);
        });
        $totalInputWeight = $totalStockWeight + $totalParentWeight;
        $totalInputCost = $totalStockCost + $totalParentCost;

        return response()->json([
            'message' => 'Datos de sources obtenidos correctamente.',
            'data' => [
                'productionRecord' => [
                    'id' => $record->id,
                    'processId' => $record->process_id,
                    'processName' => $record->process ? $record->process->name : null,
                    'productionId' => $record->production_id,
                    'productionLot' => $record->production ? $record->production->lot : null,
                    'totalInputWeight' => $totalInputWeight,
                    'totalInputCost' => $totalInputCost,
                ],
                'stockBoxes' => $stockBoxes,
                'parentOutputs' => $parentOutputs,
                'totals' => [
                    'stock' => [
                        'count' => $stockBoxes->count(),
                        'totalWeight' => $totalStockWeight,
                        'totalCost' => $totalStockCost,
                        'averageCostPerKg' => $totalStockWeight > 0 ? $totalStockCost / $totalStockWeight : null,
                    ],
                    'parent' => [
                        'count' => $parentOutputs->count(),
                        'totalWeight' => $totalParentWeight,
                        'totalCost' => $totalParentCost,
                        'averageCostPerKg' => $totalParentWeight > 0 ? $totalParentCost / $totalParentWeight : null,
                    ],
                    'combined' => [
                        'totalWeight' => $totalInputWeight,
                        'totalCost' => $totalInputCost,
                        'averageCostPerKg' => $totalInputWeight > 0 ? $totalInputCost / $totalInputWeight : null,
                    ],
                ],
            ],
        ]);
    }
}
