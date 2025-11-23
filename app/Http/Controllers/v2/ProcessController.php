<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProcessResource;
use App\Models\Process;
use Illuminate\Http\Request;

class ProcessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Process::query();

        // Filtro por tipo
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filtro por name
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Ordenar por name
        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 15);
        return ProcessResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2',
            'type' => 'required|in:starting,process,final',
        ]);

        $process = Process::create($validated);

        return response()->json([
            'message' => 'Proceso creado correctamente.',
            'data' => new ProcessResource($process),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $process = Process::findOrFail($id);

        return response()->json([
            'message' => 'Proceso obtenido correctamente.',
            'data' => new ProcessResource($process),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $process = Process::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:2',
            'type' => 'sometimes|required|in:starting,process,final',
        ]);

        $process->update($validated);

        return response()->json([
            'message' => 'Proceso actualizado correctamente.',
            'data' => new ProcessResource($process),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $process = Process::findOrFail($id);
        $process->delete();

        return response()->json([
            'message' => 'Proceso eliminado correctamente.',
        ], 200);
    }

    /**
     * Obtener opciones para seleccionar en el frontend
     */
    public function options(Request $request)
    {
        $query = Process::query();

        // Filtrar por tipo si se proporciona
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Ordenar por name
        $query->orderBy('name', 'asc');

        $processes = $query->get();

        return response()->json([
            'message' => 'Opciones de procesos obtenidas correctamente.',
            'data' => $processes->map(function ($process) {
                return [
                    'value' => $process->id,
                    'label' => $process->name,
                    'type' => $process->type,
                ];
            }),
        ]);
    }
}
