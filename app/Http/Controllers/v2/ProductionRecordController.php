<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProductionRecordResource;
use App\Models\ProductionRecord;
use Illuminate\Http\Request;

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
            'process_id' => 'nullable|exists:tenant.processes,id',
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
            'process_id' => 'sometimes|nullable|exists:tenant.processes,id',
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
}
