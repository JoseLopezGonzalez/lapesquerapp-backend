<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleFishingGearsRequest;
use App\Http\Requests\v2\StoreFishingGearRequest;
use App\Http\Requests\v2\UpdateFishingGearRequest;
use App\Http\Resources\v2\FishingGearResource;
use App\Models\FishingGear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FishingGearController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FishingGear::class);

        $query = FishingGear::query();
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }
        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        $query->orderBy('name', 'asc');
        $perPage = $request->input('perPage', 12);

        return FishingGearResource::collection($query->paginate($perPage))->response();
    }

    public function store(StoreFishingGearRequest $request): JsonResponse
    {
        $fishingGear = FishingGear::create($request->validated());

        return response()->json([
            'message' => 'Arte de pesca creado correctamente.',
            'data' => new FishingGearResource($fishingGear),
        ], 201);
    }

    public function show(FishingGear $fishingGear): JsonResponse
    {
        $this->authorize('view', $fishingGear);

        return response()->json([
            'message' => 'Arte de pesca obtenido con éxito',
            'data' => new FishingGearResource($fishingGear),
        ]);
    }

    public function update(UpdateFishingGearRequest $request, FishingGear $fishingGear): JsonResponse
    {
        $fishingGear->update($request->validated());

        return response()->json([
            'message' => 'Arte de pesca actualizado con éxito',
            'data' => new FishingGearResource($fishingGear),
        ]);
    }

    public function destroy(FishingGear $fishingGear): JsonResponse
    {
        $this->authorize('delete', $fishingGear);

        if ($fishingGear->species()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el arte de pesca porque tiene especies asociadas',
                'userMessage' => 'No se puede eliminar el arte de pesca porque tiene especies asociadas',
            ], 400);
        }

        $fishingGear->delete();

        return response()->json(['message' => 'Arte de pesca eliminado con éxito.']);
    }

    public function destroyMultiple(DestroyMultipleFishingGearsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $fishingGears = FishingGear::whereIn('id', $validated['ids'])->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($fishingGears as $fishingGear) {
            if ($fishingGear->species()->count() > 0) {
                $errors[] = "Arte de pesca '{$fishingGear->name}' no se puede eliminar porque tiene especies asociadas";
                continue;
            }
            $fishingGear->delete();
            $deletedCount++;
        }

        $message = "Se eliminaron {$deletedCount} artes de pesca con éxito";
        $userMessage = '';
        if (! empty($errors)) {
            $message .= '. Errores: ' . implode(', ', $errors);
            if ($deletedCount === 0) {
                $userMessage = count($errors) === 1 ? $errors[0] : 'No se pudieron eliminar los artes de pesca porque tienen especies asociadas';
            } else {
                $userMessage = count($errors) === 1
                    ? "Se eliminaron {$deletedCount} artes de pesca. {$errors[0]}"
                    : "Se eliminaron {$deletedCount} artes de pesca. Algunos no se pudieron eliminar porque tienen especies asociadas";
            }
        } else {
            $userMessage = "Se eliminaron {$deletedCount} artes de pesca con éxito";
        }

        return response()->json([
            'message' => $message,
            'userMessage' => $userMessage,
            'deletedCount' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewOptions', FishingGear::class);

        $fishingGears = FishingGear::select('id', 'name')->orderBy('name', 'asc')->get();

        return response()->json($fishingGears);
    }
}
