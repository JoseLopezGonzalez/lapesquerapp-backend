<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleIncotermsRequest;
use App\Http\Requests\v2\StoreIncotermRequest;
use App\Http\Requests\v2\UpdateIncotermRequest;
use App\Http\Resources\v2\IncotermResource;
use App\Models\Incoterm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncotermController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Incoterm::class);

        $query = Incoterm::query();
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }
        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }
        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->code . '%');
        }
        if ($request->has('description')) {
            $query->where('description', 'like', '%' . $request->description . '%');
        }
        $query->orderBy('code', 'asc');
        $perPage = $request->input('perPage', 10);

        return IncotermResource::collection($query->paginate($perPage))->response();
    }

    public function store(StoreIncotermRequest $request): JsonResponse
    {
        $incoterm = Incoterm::create($request->validated());

        return response()->json([
            'message' => 'Incoterm creado correctamente.',
            'data' => new IncotermResource($incoterm),
        ], 201);
    }

    public function show(Incoterm $incoterm): JsonResponse
    {
        $this->authorize('view', $incoterm);

        return response()->json([
            'message' => 'Incoterm obtenido con éxito',
            'data' => new IncotermResource($incoterm),
        ]);
    }

    public function update(UpdateIncotermRequest $request, Incoterm $incoterm): JsonResponse
    {
        $incoterm->update($request->validated());

        return response()->json([
            'message' => 'Incoterm actualizado con éxito',
            'data' => new IncotermResource($incoterm),
        ]);
    }

    public function destroy(Incoterm $incoterm): JsonResponse
    {
        $this->authorize('delete', $incoterm);

        if ($incoterm->orders()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el incoterm porque está en uso',
                'details' => 'El incoterm está siendo utilizado en pedidos',
                'userMessage' => 'No se puede eliminar el incoterm porque está siendo utilizado en pedidos',
            ], 400);
        }

        $incoterm->delete();

        return response()->json(['message' => 'Incoterm eliminado con éxito.']);
    }

    public function destroyMultiple(DestroyMultipleIncotermsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $incoterms = Incoterm::whereIn('id', $validated['ids'])->get();

        $inUse = [];
        foreach ($incoterms as $incoterm) {
            if ($incoterm->orders()->exists()) {
                $inUse[] = ['id' => $incoterm->id, 'code' => $incoterm->code, 'reasons' => 'pedidos'];
            }
        }

        if (! empty($inUse)) {
            $details = array_map(fn ($item) => $item['code'] . ' (usado en: ' . $item['reasons'] . ')', $inUse);

            return response()->json([
                'message' => 'No se pueden eliminar algunos incoterms porque están en uso',
                'details' => implode(', ', $details),
                'userMessage' => 'No se pueden eliminar algunos incoterms porque están en uso: ' . implode(', ', array_column($inUse, 'code')),
            ], 400);
        }

        Incoterm::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Incoterms eliminados con éxito.']);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewOptions', Incoterm::class);

        $incoterms = Incoterm::select('id', 'code', 'description')->get()
            ->map(fn ($i) => ['id' => $i->id, 'name' => "{$i->code} - {$i->description}"]);

        return response()->json($incoterms);
    }
}
