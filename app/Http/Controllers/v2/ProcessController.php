<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\IndexProcessRequest;
use App\Http\Requests\v2\ProcessOptionsRequest;
use App\Http\Requests\v2\StoreProcessRequest;
use App\Http\Requests\v2\UpdateProcessRequest;
use App\Http\Resources\v2\ProcessResource;
use App\Models\Process;

class ProcessController extends Controller
{
    public function index(IndexProcessRequest $request)
    {
        $query = Process::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $query->orderBy('name', 'asc');
        $perPage = $request->input('perPage', 15);

        return ProcessResource::collection($query->paginate($perPage));
    }

    public function store(StoreProcessRequest $request)
    {
        $process = Process::create($request->validated());

        return response()->json([
            'message' => 'Proceso creado correctamente.',
            'data' => new ProcessResource($process),
        ], 201);
    }

    public function show(string $id)
    {
        $process = Process::findOrFail($id);
        $this->authorize('view', $process);

        return response()->json([
            'message' => 'Proceso obtenido correctamente.',
            'data' => new ProcessResource($process),
        ]);
    }

    public function update(UpdateProcessRequest $request, string $id)
    {
        $process = Process::findOrFail($id);
        $this->authorize('update', $process);
        $process->update($request->validated());

        return response()->json([
            'message' => 'Proceso actualizado correctamente.',
            'data' => new ProcessResource($process),
        ]);
    }

    public function destroy(string $id)
    {
        $process = Process::findOrFail($id);
        $this->authorize('delete', $process);
        $process->delete();

        return response()->json([
            'message' => 'Proceso eliminado correctamente.',
        ], 200);
    }

    public function options(ProcessOptionsRequest $request)
    {
        $query = Process::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $query->orderBy('name', 'asc');
        $processes = $query->get();

        return response()->json([
            'message' => 'Opciones de procesos obtenidas correctamente.',
            'data' => $processes->map(fn ($process) => [
                'value' => $process->id,
                'label' => $process->name,
                'type' => $process->type,
            ]),
        ]);
    }
}
