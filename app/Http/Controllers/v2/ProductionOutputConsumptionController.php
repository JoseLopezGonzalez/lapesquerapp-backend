<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProductionOutputConsumptionResource;
use App\Models\ProductionOutputConsumption;
use App\Models\ProductionRecord;
use App\Models\ProductionOutput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionOutputConsumptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProductionOutputConsumption::query();

        // Cargar relaciones
        $query->with(['productionRecord.process', 'productionOutput.product']);

        // Filtro por production_record_id (proceso que consume)
        if ($request->has('production_record_id')) {
            $query->where('production_record_id', $request->production_record_id);
        }

        // Filtro por production_output_id (output del padre)
        if ($request->has('production_output_id')) {
            $query->where('production_output_id', $request->production_output_id);
        }

        // Filtro por production_id (a través de production_record)
        if ($request->has('production_id')) {
            $query->whereHas('productionRecord', function ($q) use ($request) {
                $q->where('production_id', $request->production_id);
            });
        }

        // Filtro por parent_record_id (procesos hijos de un proceso específico)
        if ($request->has('parent_record_id')) {
            $query->whereHas('productionRecord', function ($q) use ($request) {
                $q->where('parent_record_id', $request->parent_record_id);
            });
        }

        $perPage = $request->input('perPage', 15);
        return ProductionOutputConsumptionResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'production_record_id' => 'required|exists:tenant.production_records,id',
            'production_output_id' => 'required|exists:tenant.production_outputs,id',
            'consumed_weight_kg' => 'required|numeric|min:0',
            'consumed_boxes' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $record = ProductionRecord::findOrFail($validated['production_record_id']);
        $output = ProductionOutput::findOrFail($validated['production_output_id']);

        // Validar que el proceso tenga un padre
        if (!$record->parent_record_id) {
            return response()->json([
                'message' => 'El proceso no tiene un proceso padre. Solo los procesos hijos pueden consumir outputs de procesos padre.',
            ], 422);
        }

        // Validar que el output pertenezca al proceso padre directo
        if ($record->parent_record_id !== $output->production_record_id) {
            return response()->json([
                'message' => 'El output debe pertenecer al proceso padre directo del proceso que consume.',
            ], 422);
        }

        // Validar que no haya un consumo ya existente para este output en este proceso
        $existing = ProductionOutputConsumption::where('production_record_id', $validated['production_record_id'])
            ->where('production_output_id', $validated['production_output_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Ya existe un consumo de este output para este proceso. Use el método update para modificarlo.',
            ], 422);
        }

        // Validar que el consumo no exceda el output disponible
        $totalConsumed = ProductionOutputConsumption::where('production_output_id', $validated['production_output_id'])
            ->sum('consumed_weight_kg');

        $availableWeight = $output->weight_kg - $totalConsumed;

        if ($validated['consumed_weight_kg'] > $availableWeight) {
            return response()->json([
                'message' => "No hay suficiente peso disponible en el output. Disponible: {$availableWeight}kg, solicitado: {$validated['consumed_weight_kg']}kg",
                'available_weight' => $availableWeight,
                'requested_weight' => $validated['consumed_weight_kg'],
            ], 422);
        }

        // Validar cajas si se especifican
        if (isset($validated['consumed_boxes']) && $validated['consumed_boxes'] > 0) {
            $totalConsumedBoxes = ProductionOutputConsumption::where('production_output_id', $validated['production_output_id'])
                ->sum('consumed_boxes');

            $availableBoxes = $output->boxes - $totalConsumedBoxes;

            if ($validated['consumed_boxes'] > $availableBoxes) {
                return response()->json([
                    'message' => "No hay suficientes cajas disponibles en el output. Disponible: {$availableBoxes}, solicitado: {$validated['consumed_boxes']}",
                    'available_boxes' => $availableBoxes,
                    'requested_boxes' => $validated['consumed_boxes'],
                ], 422);
            }
        }

        // Establecer consumed_boxes en 0 si no se especifica
        if (!isset($validated['consumed_boxes'])) {
            $validated['consumed_boxes'] = 0;
        }

        $consumption = ProductionOutputConsumption::create($validated);

        // Cargar relaciones para la respuesta
        $consumption->load(['productionRecord.process', 'productionOutput.product']);

        return response()->json([
            'message' => 'Consumo de output creado correctamente.',
            'data' => new ProductionOutputConsumptionResource($consumption),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $consumption = ProductionOutputConsumption::with(['productionRecord.process', 'productionOutput.product'])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Consumo de output obtenido correctamente.',
            'data' => new ProductionOutputConsumptionResource($consumption),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $consumption = ProductionOutputConsumption::findOrFail($id);
        $output = $consumption->productionOutput;

        $validated = $request->validate([
            'consumed_weight_kg' => 'sometimes|numeric|min:0',
            'consumed_boxes' => 'sometimes|nullable|integer|min:0',
            'notes' => 'sometimes|nullable|string',
        ]);

        // Si se actualiza el peso, validar disponibilidad
        if (isset($validated['consumed_weight_kg'])) {
            $totalConsumed = ProductionOutputConsumption::where('production_output_id', $consumption->production_output_id)
                ->where('id', '!=', $id)
                ->sum('consumed_weight_kg');

            $availableWeight = $output->weight_kg - $totalConsumed;

            if ($validated['consumed_weight_kg'] > $availableWeight) {
                return response()->json([
                    'message' => "No hay suficiente peso disponible. Disponible: {$availableWeight}kg, solicitado: {$validated['consumed_weight_kg']}kg",
                    'available_weight' => $availableWeight,
                    'requested_weight' => $validated['consumed_weight_kg'],
                ], 422);
            }
        }

        // Si se actualizan las cajas, validar disponibilidad
        if (isset($validated['consumed_boxes']) && $validated['consumed_boxes'] > 0) {
            $totalConsumedBoxes = ProductionOutputConsumption::where('production_output_id', $consumption->production_output_id)
                ->where('id', '!=', $id)
                ->sum('consumed_boxes');

            $availableBoxes = $output->boxes - $totalConsumedBoxes;

            if ($validated['consumed_boxes'] > $availableBoxes) {
                return response()->json([
                    'message' => "No hay suficientes cajas disponibles. Disponible: {$availableBoxes}, solicitado: {$validated['consumed_boxes']}",
                    'available_boxes' => $availableBoxes,
                    'requested_boxes' => $validated['consumed_boxes'],
                ], 422);
            }
        }

        $consumption->update($validated);

        // Cargar relaciones para la respuesta
        $consumption->load(['productionRecord.process', 'productionOutput.product']);

        return response()->json([
            'message' => 'Consumo de output actualizado correctamente.',
            'data' => new ProductionOutputConsumptionResource($consumption),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $consumption = ProductionOutputConsumption::findOrFail($id);
        $consumption->delete();

        return response()->json([
            'message' => 'Consumo de output eliminado correctamente.',
        ], 200);
    }

    /**
     * Store multiple consumptions at once
     */
    public function storeMultiple(Request $request)
    {
        $validated = $request->validate([
            'production_record_id' => 'required|exists:tenant.production_records,id',
            'consumptions' => 'required|array|min:1',
            'consumptions.*.production_output_id' => 'required|exists:tenant.production_outputs,id',
            'consumptions.*.consumed_weight_kg' => 'required|numeric|min:0',
            'consumptions.*.consumed_boxes' => 'nullable|integer|min:0',
            'consumptions.*.notes' => 'nullable|string',
        ]);

        $record = ProductionRecord::findOrFail($validated['production_record_id']);

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

        $created = [];
        $errors = [];

        DB::beginTransaction();
        try {
            // Validar disponibilidad de outputs antes de crear
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

            // Validar cada output
            foreach ($outputsToValidate as $outputId => $validationData) {
                $output = $validationData['output'];

                // Validar que el output pertenezca al proceso padre directo
                if ($output->production_record_id !== $parent->id) {
                    $errors[] = "El output #{$outputId} no pertenece al proceso padre directo.";
                    continue;
                }

                // Calcular consumo actual
                $currentConsumedWeight = ProductionOutputConsumption::where('production_output_id', $outputId)
                    ->sum('consumed_weight_kg');

                $currentConsumedBoxes = ProductionOutputConsumption::where('production_output_id', $outputId)
                    ->sum('consumed_boxes');

                // Calcular disponibilidad
                $availableWeight = $output->weight_kg - $currentConsumedWeight;
                $availableBoxes = $output->boxes - $currentConsumedBoxes;

                // Validar disponibilidad
                if ($validationData['total_requested_weight'] > $availableWeight) {
                    $errors[] = "Output #{$outputId}: No hay suficiente peso disponible. Disponible: {$availableWeight}kg, solicitado: {$validationData['total_requested_weight']}kg";
                }

                if ($validationData['total_requested_boxes'] > $availableBoxes) {
                    $errors[] = "Output #{$outputId}: No hay suficientes cajas disponibles. Disponible: {$availableBoxes}, solicitado: {$validationData['total_requested_boxes']}";
                }
            }

            // Si hay errores de validación, hacer rollback
            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Error de validación al crear los consumos.',
                    'errors' => $errors,
                ], 422);
            }

            // Crear consumos
            foreach ($validated['consumptions'] as $index => $consumptionData) {
                try {
                    // Verificar que no haya un consumo ya existente para este output en este proceso
                    $existing = ProductionOutputConsumption::where('production_record_id', $validated['production_record_id'])
                        ->where('production_output_id', $consumptionData['production_output_id'])
                        ->first();

                    if ($existing) {
                        $errors[] = "Consumo #{$index}: Ya existe un consumo para el output #{$consumptionData['production_output_id']}.";
                        continue;
                    }

                    $consumption = ProductionOutputConsumption::create([
                        'production_record_id' => $validated['production_record_id'],
                        'production_output_id' => $consumptionData['production_output_id'],
                        'consumed_weight_kg' => $consumptionData['consumed_weight_kg'],
                        'consumed_boxes' => $consumptionData['consumed_boxes'] ?? 0,
                        'notes' => $consumptionData['notes'] ?? null,
                    ]);

                    $consumption->load(['productionRecord.process', 'productionOutput.product']);
                    $created[] = new ProductionOutputConsumptionResource($consumption);
                } catch (\Exception $e) {
                    $errors[] = "Error en el consumo #{$index}: " . $e->getMessage();
                }
            }

            // Si no se creó ninguno y hay errores, hacer rollback
            if (empty($created) && !empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se pudo crear ningún consumo.',
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();

            return response()->json([
                'message' => count($created) . ' consumo(s) creado(s) correctamente.',
                'data' => $created,
                'errors' => $errors,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear los consumos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener outputs disponibles para consumo por un proceso hijo
     */
    public function getAvailableOutputs(string $productionRecordId)
    {
        $record = ProductionRecord::findOrFail($productionRecordId);

        if (!$record->parent_record_id) {
            return response()->json([
                'message' => 'El proceso no tiene un proceso padre.',
                'data' => [],
            ]);
        }

        $parent = $record->parent;
        if (!$parent) {
            return response()->json([
                'message' => 'El proceso padre no existe.',
                'data' => [],
            ]);
        }

        $outputs = $parent->outputs()->with('product')->get();

        $availableOutputs = $outputs->map(function ($output) {
            $consumedWeight = ProductionOutputConsumption::where('production_output_id', $output->id)
                ->sum('consumed_weight_kg');

            $consumedBoxes = ProductionOutputConsumption::where('production_output_id', $output->id)
                ->sum('consumed_boxes');

            $availableWeight = max(0, $output->weight_kg - $consumedWeight);
            $availableBoxes = max(0, $output->boxes - $consumedBoxes);

            return [
                'output' => new \App\Http\Resources\v2\ProductionOutputResource($output),
                'totalWeight' => $output->weight_kg,
                'totalBoxes' => $output->boxes,
                'consumedWeight' => $consumedWeight,
                'consumedBoxes' => $consumedBoxes,
                'availableWeight' => $availableWeight,
                'availableBoxes' => $availableBoxes,
                'hasExistingConsumption' => ProductionOutputConsumption::where('production_record_id', request()->input('production_record_id'))
                    ->where('production_output_id', $output->id)
                    ->exists(),
            ];
        })->filter(function ($item) {
            return $item['availableWeight'] > 0 || $item['availableBoxes'] > 0;
        })->values();

        return response()->json([
            'message' => 'Outputs disponibles obtenidos correctamente.',
            'data' => $availableOutputs,
        ]);
    }
}

