<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProductionInputResource;
use App\Models\ProductionInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionInputController extends Controller
{
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

        $perPage = $request->input('perPage', 15);
        return ProductionInputResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'production_record_id' => 'required|exists:tenant.production_records,id',
            'box_id' => 'required|exists:tenant.boxes,id',
        ]);

        // Verificar que la caja no esté ya asignada a este proceso
        $existing = ProductionInput::where('production_record_id', $validated['production_record_id'])
            ->where('box_id', $validated['box_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'La caja ya está asignada a este proceso.',
            ], 422);
        }

        $input = ProductionInput::create($validated);

        // Cargar relaciones para la respuesta
        $input->load(['productionRecord', 'box.product']);

        return response()->json([
            'message' => 'Entrada de producción creada correctamente.',
            'data' => new ProductionInputResource($input),
        ], 201);
    }

    /**
     * Store multiple inputs at once
     */
    public function storeMultiple(Request $request)
    {
        $validated = $request->validate([
            'production_record_id' => 'required|exists:tenant.production_records,id',
            'box_ids' => 'required|array',
            'box_ids.*' => 'required|exists:tenant.boxes,id',
        ]);

        $created = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($validated['box_ids'] as $boxId) {
                // Verificar que la caja no esté ya asignada
                $existing = ProductionInput::where('production_record_id', $validated['production_record_id'])
                    ->where('box_id', $boxId)
                    ->first();

                if ($existing) {
                    $errors[] = "La caja {$boxId} ya está asignada a este proceso.";
                    continue;
                }

                $input = ProductionInput::create([
                    'production_record_id' => $validated['production_record_id'],
                    'box_id' => $boxId,
                ]);

                $input->load(['productionRecord', 'box.product']);
                $created[] = new ProductionInputResource($input);
            }

            DB::commit();

            return response()->json([
                'message' => count($created) . ' entradas creadas correctamente.',
                'data' => $created,
                'errors' => $errors,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
        $input->delete();

        return response()->json([
            'message' => 'Entrada de producción eliminada correctamente.',
        ], 200);
    }
}
