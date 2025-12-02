<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProductionRecordResource;
use App\Http\Resources\v2\ProductionOutputResource;
use App\Models\ProductionRecord;
use App\Models\ProductionOutput;
use App\Models\ProductionOutputConsumption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProductionRecord::query();

        // Cargar relaciones
        $query->with(['production', 'parent', 'process', 'inputs.box.product', 'outputs.product']);

        // Filtro por production_id
        if ($request->has('production_id')) {
            $query->where('production_id', $request->production_id);
        }

        // Filtro por parent_record_id (null para raíces)
        if ($request->has('root_only')) {
            $query->whereNull('parent_record_id');
        }

        // Filtro por parent_record_id específico
        if ($request->has('parent_record_id')) {
            $query->where('parent_record_id', $request->parent_record_id);
        }

        // Filtro por process_id
        if ($request->has('process_id')) {
            $query->where('process_id', $request->process_id);
        }

        // Filtro por estado (completado o no)
        if ($request->has('completed')) {
            if ($request->completed === 'true' || $request->completed === true) {
                $query->whereNotNull('finished_at');
            } else {
                $query->whereNull('finished_at');
            }
        }

        // Ordenar por started_at
        $query->orderBy('started_at', 'desc');

        $perPage = $request->input('perPage', 15);
        return ProductionRecordResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'production_id' => 'required|exists:tenant.productions,id',
            'parent_record_id' => 'nullable|exists:tenant.production_records,id',
            'process_id' => 'required|exists:tenant.processes,id',
            'started_at' => 'nullable|date',
            'finished_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $record = ProductionRecord::create($validated);

        // Cargar relaciones para la respuesta
        $record->load(['production', 'parent', 'process', 'inputs', 'outputs']);

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
            'outputs.product'
        ])->findOrFail($id);

        return response()->json([
            'message' => 'Registro de producción obtenido correctamente.',
            'data' => new ProductionRecordResource($record),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $record = ProductionRecord::findOrFail($id);

        $validated = $request->validate([
            'production_id' => 'sometimes|exists:tenant.productions,id',
            'parent_record_id' => 'sometimes|nullable|exists:tenant.production_records,id',
            'process_id' => 'sometimes|required|exists:tenant.processes,id',
            'started_at' => 'sometimes|nullable|date',
            'finished_at' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string',
        ]);

        $record->update($validated);

        // Cargar relaciones para la respuesta
        $record->load(['production', 'parent', 'process', 'inputs', 'outputs']);

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
        $record->delete();

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
    public function finish(string $id)
    {
        $record = ProductionRecord::findOrFail($id);

        if ($record->finished_at) {
            return response()->json([
                'message' => 'El proceso ya está finalizado.',
            ], 400);
        }

        $record->update(['finished_at' => now()]);

        $record->load(['production', 'parent', 'process', 'inputs', 'outputs']);

        return response()->json([
            'message' => 'Proceso finalizado correctamente.',
            'data' => new ProductionRecordResource($record),
        ]);
    }

    /**
     * Sincronizar todas las salidas de un proceso
     * Crea nuevas, actualiza existentes y elimina las que no están en el array
     */
    public function syncOutputs(Request $request, string $id)
    {
        $record = ProductionRecord::findOrFail($id);

        $validated = $request->validate([
            'outputs' => 'required|array',
            'outputs.*.id' => 'sometimes|nullable|integer|exists:tenant.production_outputs,id',
            'outputs.*.product_id' => 'required|exists:tenant.products,id',
            'outputs.*.lot_id' => 'nullable|string',
            'outputs.*.boxes' => 'required|integer|min:0',
            'outputs.*.weight_kg' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $existingOutputIds = $record->outputs()->pluck('id')->toArray();
            $providedOutputIds = collect($validated['outputs'])
                ->pluck('id')
                ->filter()
                ->map(fn($id) => (int)$id)
                ->toArray();

            // Validar que los outputs proporcionados pertenezcan al proceso
            foreach ($providedOutputIds as $outputId) {
                $output = ProductionOutput::find($outputId);
                if ($output && $output->production_record_id != $record->id) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "El output #{$outputId} no pertenece a este proceso.",
                    ], 422);
                }
            }

            // Validar que no se eliminen outputs que tienen consumos
            $outputsToDelete = array_diff($existingOutputIds, $providedOutputIds);
            foreach ($outputsToDelete as $outputId) {
                $output = ProductionOutput::find($outputId);
                if ($output && $output->consumptions()->exists()) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "No se puede eliminar el output #{$outputId} porque tiene consumos asociados.",
                        'output_id' => $outputId,
                    ], 422);
                }
            }

            $created = [];
            $updated = [];
            $deleted = [];

            // Procesar cada output del array
            foreach ($validated['outputs'] as $outputData) {
                if (isset($outputData['id']) && in_array($outputData['id'], $existingOutputIds)) {
                    // Actualizar existente
                    $output = ProductionOutput::find($outputData['id']);
                    $output->update([
                        'product_id' => $outputData['product_id'],
                        'lot_id' => $outputData['lot_id'] ?? null,
                        'boxes' => $outputData['boxes'],
                        'weight_kg' => $outputData['weight_kg'],
                    ]);
                    $output->load(['productionRecord', 'product']);
                    $updated[] = new ProductionOutputResource($output);
                } else {
                    // Crear nuevo
                    $output = ProductionOutput::create([
                        'production_record_id' => $record->id,
                        'product_id' => $outputData['product_id'],
                        'lot_id' => $outputData['lot_id'] ?? null,
                        'boxes' => $outputData['boxes'],
                        'weight_kg' => $outputData['weight_kg'],
                    ]);
                    $output->load(['productionRecord', 'product']);
                    $created[] = new ProductionOutputResource($output);
                }
            }

            // Eliminar outputs que no están en el array
            foreach ($outputsToDelete as $outputId) {
                $output = ProductionOutput::find($outputId);
                if ($output) {
                    $output->delete();
                    $deleted[] = $outputId;
                }
            }

            DB::commit();

            $record->refresh();
            $record->load(['production', 'parent', 'process', 'inputs', 'outputs.product']);

            return response()->json([
                'message' => 'Salidas sincronizadas correctamente.',
                'data' => new ProductionRecordResource($record),
                'summary' => [
                    'created' => count($created),
                    'updated' => count($updated),
                    'deleted' => count($deleted),
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al sincronizar las salidas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sincronizar todos los consumos de outputs del padre de un proceso
     * Crea nuevos, actualiza existentes y elimina los que no están en el array
     */
    public function syncConsumptions(Request $request, string $id)
    {
        $record = ProductionRecord::findOrFail($id);

        // Validar que el proceso tenga un padre
        if (!$record->parent_record_id) {
            return response()->json([
                'message' => 'El proceso no tiene un proceso padre. Solo los procesos hijos pueden consumir outputs de procesos padre.',
            ], 422);
        }

        $parent = $record->parent;
        if (!$parent) {
            return response()->json([
                'message' => 'El proceso padre no existe.',
            ], 422);
        }

        $validated = $request->validate([
            'consumptions' => 'required|array',
            'consumptions.*.id' => 'sometimes|nullable|integer|exists:tenant.production_output_consumptions,id',
            'consumptions.*.production_output_id' => 'required|exists:tenant.production_outputs,id',
            'consumptions.*.consumed_weight_kg' => 'required|numeric|min:0',
            'consumptions.*.consumed_boxes' => 'nullable|integer|min:0',
            'consumptions.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $existingConsumptionIds = $record->parentOutputConsumptions()->pluck('id')->toArray();
            $providedConsumptionIds = collect($validated['consumptions'])
                ->pluck('id')
                ->filter()
                ->map(fn($id) => (int)$id)
                ->toArray();

            // Validar que los consumos proporcionados pertenezcan al proceso
            foreach ($providedConsumptionIds as $consumptionId) {
                $consumption = \App\Models\ProductionOutputConsumption::find($consumptionId);
                if ($consumption && $consumption->production_record_id != $record->id) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "El consumo #{$consumptionId} no pertenece a este proceso.",
                    ], 422);
                }
            }

            // Validar cada consumo antes de procesar
            $outputsToValidate = [];
            foreach ($validated['consumptions'] as $consumptionData) {
                $outputId = $consumptionData['production_output_id'];
                if (!isset($outputsToValidate[$outputId])) {
                    $outputsToValidate[$outputId] = [
                        'output' => ProductionOutput::findOrFail($outputId),
                        'total_requested_weight' => 0,
                        'total_requested_boxes' => 0,
                    ];
                }
                $outputsToValidate[$outputId]['total_requested_weight'] += $consumptionData['consumed_weight_kg'];
                $outputsToValidate[$outputId]['total_requested_boxes'] += ($consumptionData['consumed_boxes'] ?? 0);
            }

            // Validar disponibilidad de cada output
            foreach ($outputsToValidate as $outputId => $validationData) {
                $output = $validationData['output'];

                // Validar que el output pertenezca al proceso padre directo
                if ($output->production_record_id !== $parent->id) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "El output #{$outputId} no pertenece al proceso padre directo.",
                    ], 422);
                }

                // Calcular consumo actual (excluyendo los que se van a actualizar)
                $currentConsumedWeight = ProductionOutputConsumption::where('production_output_id', $outputId)
                    ->where('production_record_id', '!=', $record->id)
                    ->sum('consumed_weight_kg');

                $currentConsumedBoxes = ProductionOutputConsumption::where('production_output_id', $outputId)
                    ->where('production_record_id', '!=', $record->id)
                    ->sum('consumed_boxes');

                // Calcular disponibilidad
                $availableWeight = $output->weight_kg - $currentConsumedWeight;
                $availableBoxes = $output->boxes - $currentConsumedBoxes;

                // Validar que el consumo total no exceda lo disponible
                if ($validationData['total_requested_weight'] > $availableWeight) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "No hay suficiente peso disponible en el output #{$outputId}. Disponible: {$availableWeight}kg, solicitado: {$validationData['total_requested_weight']}kg",
                        'output_id' => $outputId,
                        'available_weight' => $availableWeight,
                        'requested_weight' => $validationData['total_requested_weight'],
                    ], 422);
                }

                if ($validationData['total_requested_boxes'] > $availableBoxes) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "No hay suficientes cajas disponibles en el output #{$outputId}. Disponible: {$availableBoxes}, solicitado: {$validationData['total_requested_boxes']}",
                        'output_id' => $outputId,
                        'available_boxes' => $availableBoxes,
                        'requested_boxes' => $validationData['total_requested_boxes'],
                    ], 422);
                }
            }

            $created = [];
            $updated = [];
            $deleted = [];

            // Procesar cada consumo del array
            foreach ($validated['consumptions'] as $consumptionData) {
                if (isset($consumptionData['id']) && in_array($consumptionData['id'], $existingConsumptionIds)) {
                    // Actualizar existente
                    $consumption = \App\Models\ProductionOutputConsumption::find($consumptionData['id']);
                    $consumption->update([
                        'production_output_id' => $consumptionData['production_output_id'],
                        'consumed_weight_kg' => $consumptionData['consumed_weight_kg'],
                        'consumed_boxes' => $consumptionData['consumed_boxes'] ?? 0,
                        'notes' => $consumptionData['notes'] ?? null,
                    ]);
                    $consumption->load(['productionRecord.process', 'productionOutput.product']);
                    $updated[] = new \App\Http\Resources\v2\ProductionOutputConsumptionResource($consumption);
                } else {
                    // Validar que no haya duplicado
                    $existing = \App\Models\ProductionOutputConsumption::where('production_record_id', $record->id)
                        ->where('production_output_id', $consumptionData['production_output_id'])
                        ->first();

                    if ($existing) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Ya existe un consumo para el output #{$consumptionData['production_output_id']}. Use el ID del consumo existente para actualizarlo.",
                            'existing_consumption_id' => $existing->id,
                        ], 422);
                    }

                    // Crear nuevo
                    $consumption = \App\Models\ProductionOutputConsumption::create([
                        'production_record_id' => $record->id,
                        'production_output_id' => $consumptionData['production_output_id'],
                        'consumed_weight_kg' => $consumptionData['consumed_weight_kg'],
                        'consumed_boxes' => $consumptionData['consumed_boxes'] ?? 0,
                        'notes' => $consumptionData['notes'] ?? null,
                    ]);
                    $consumption->load(['productionRecord.process', 'productionOutput.product']);
                    $created[] = new \App\Http\Resources\v2\ProductionOutputConsumptionResource($consumption);
                }
            }

            // Eliminar consumos que no están en el array
            $consumptionsToDelete = array_diff($existingConsumptionIds, $providedConsumptionIds);
            foreach ($consumptionsToDelete as $consumptionId) {
                $consumption = \App\Models\ProductionOutputConsumption::find($consumptionId);
                if ($consumption) {
                    $consumption->delete();
                    $deleted[] = $consumptionId;
                }
            }

            DB::commit();

            $record->refresh();
            $record->load([
                'production',
                'parent',
                'process',
                'inputs.box.product',
                'outputs.product',
                'parentOutputConsumptions.productionOutput.product'
            ]);

            return response()->json([
                'message' => 'Consumos sincronizados correctamente.',
                'data' => new ProductionRecordResource($record),
                'summary' => [
                    'created' => count($created),
                    'updated' => count($updated),
                    'deleted' => count($deleted),
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al sincronizar los consumos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
