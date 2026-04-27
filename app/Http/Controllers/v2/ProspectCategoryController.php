<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\StoreProspectCategoryRequest;
use App\Http\Requests\v2\UpdateProspectCategoryRequest;
use App\Http\Resources\v2\ProspectCategoryResource;
use App\Models\ProspectCategory;
use App\Services\v2\ProspectCategoryListService;
use Illuminate\Http\JsonResponse;

class ProspectCategoryController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', ProspectCategory::class);

        return ProspectCategoryResource::collection(ProspectCategoryListService::list(request()));
    }

    public function store(StoreProspectCategoryRequest $request): JsonResponse
    {
        $category = ProspectCategory::create($request->validated());

        return response()->json([
            'message' => 'Categoría de prospecto creada correctamente.',
            'data' => new ProspectCategoryResource($category),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $category = ProspectCategory::findOrFail($id);
        $this->authorize('view', $category);

        return response()->json([
            'message' => 'Categoría de prospecto obtenida correctamente.',
            'data' => new ProspectCategoryResource($category),
        ]);
    }

    public function update(UpdateProspectCategoryRequest $request, string $id): JsonResponse
    {
        $category = ProspectCategory::findOrFail($id);
        $this->authorize('update', $category);
        $category->update($request->validated());

        return response()->json([
            'message' => 'Categoría de prospecto actualizada correctamente.',
            'data' => new ProspectCategoryResource($category),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = ProspectCategory::findOrFail($id);
        $this->authorize('delete', $category);

        if (! $category->canBeDeleted()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene prospectos asociados.',
                'userMessage' => 'No se puede eliminar la categoría porque tiene prospectos asociados.',
            ], 400);
        }

        $category->delete();

        return response()->json(['message' => 'Categoría de prospecto eliminada correctamente.']);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewOptions', ProspectCategory::class);

        $categories = ProspectCategory::where('active', true)
            ->select('id', 'name', 'description')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }
}
