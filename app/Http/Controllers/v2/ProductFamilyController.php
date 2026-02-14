<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleProductFamiliesRequest;
use App\Http\Requests\v2\StoreProductFamilyRequest;
use App\Http\Requests\v2\UpdateProductFamilyRequest;
use App\Http\Resources\v2\ProductFamilyResource;
use App\Models\ProductFamily;
use App\Services\v2\ProductFamilyListService;
use Illuminate\Http\JsonResponse;

class ProductFamilyController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', ProductFamily::class);
        return ProductFamilyResource::collection(ProductFamilyListService::list(request()));
    }

    public function store(StoreProductFamilyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $family = ProductFamily::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'category_id' => $validated['categoryId'],
            'active' => $validated['active'] ?? true,
        ]);
        $family->load('category');
        return response()->json([
            'message' => 'Familia de producto creada con éxito',
            'data' => new ProductFamilyResource($family),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $family = ProductFamily::with(['category', 'products'])->findOrFail($id);
        $this->authorize('view', $family);
        return response()->json([
            'message' => 'Familia de producto obtenida con éxito',
            'data' => new ProductFamilyResource($family),
        ]);
    }

    public function update(UpdateProductFamilyRequest $request, string $id): JsonResponse
    {
        $family = ProductFamily::findOrFail($id);
        $this->authorize('update', $family);
        $family->update($request->getUpdateData());
        $family->load('category');
        return response()->json([
            'message' => 'Familia de producto actualizada con éxito',
            'data' => new ProductFamilyResource($family),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $family = ProductFamily::findOrFail($id);
        $this->authorize('delete', $family);
        if (! $family->canBeDeleted()) {
            return response()->json([
                'message' => 'No se puede eliminar la familia porque tiene productos asociados',
                'userMessage' => 'No se puede eliminar la familia porque tiene productos asociados',
            ], 400);
        }
        $family->delete();
        return response()->json(['message' => 'Familia de producto eliminada con éxito']);
    }

    public function destroyMultiple(DestroyMultipleProductFamiliesRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];
        $families = ProductFamily::whereIn('id', $ids)->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($families as $family) {
            $this->authorize('delete', $family);
            if (! $family->canBeDeleted()) {
                $errors[] = "Familia '{$family->name}' no se puede eliminar porque tiene productos asociados";
                continue;
            }
            $family->delete();
            $deletedCount++;
        }

        $message = "Se eliminaron {$deletedCount} familias con éxito";
        $userMessage = $deletedCount === 0 && count($errors) > 0
            ? (count($errors) === 1 ? $errors[0] : 'No se pudieron eliminar las familias porque tienen productos asociados')
            : (count($errors) > 0 ? "Se eliminaron {$deletedCount} familias. " . (count($errors) === 1 ? $errors[0] : 'Algunas no se pudieron eliminar porque tienen productos asociados') : "Se eliminaron {$deletedCount} familias con éxito");

        return response()->json([
            'message' => $message . (count($errors) > 0 ? '. Errores: ' . implode(', ', $errors) : ''),
            'userMessage' => $userMessage,
            'deletedCount' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', ProductFamily::class);
        $families = ProductFamily::where('active', true)
            ->with('category:id,name')
            ->select('id', 'name', 'description', 'category_id')
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn ($family) => [
                'id' => $family->id,
                'name' => $family->name,
                'description' => $family->description,
                'categoryId' => $family->category_id,
                'categoryName' => $family->category->name ?? null,
            ]);
        return response()->json($families);
    }
}
