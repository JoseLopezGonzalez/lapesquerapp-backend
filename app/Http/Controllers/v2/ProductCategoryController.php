<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleProductCategoriesRequest;
use App\Http\Requests\v2\StoreProductCategoryRequest;
use App\Http\Requests\v2\UpdateProductCategoryRequest;
use App\Http\Resources\v2\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Services\v2\ProductCategoryListService;
use Illuminate\Http\JsonResponse;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', ProductCategory::class);
        return ProductCategoryResource::collection(ProductCategoryListService::list(request()));
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $category = ProductCategory::create($request->validated());
        return response()->json([
            'message' => 'Categoría de producto creada con éxito',
            'data' => new ProductCategoryResource($category),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $category = ProductCategory::with('families')->findOrFail($id);
        $this->authorize('view', $category);
        return response()->json([
            'message' => 'Categoría de producto obtenida con éxito',
            'data' => new ProductCategoryResource($category),
        ]);
    }

    public function update(UpdateProductCategoryRequest $request, string $id): JsonResponse
    {
        $category = ProductCategory::findOrFail($id);
        $this->authorize('update', $category);
        $category->update($request->validated());
        return response()->json([
            'message' => 'Categoría de producto actualizada con éxito',
            'data' => new ProductCategoryResource($category),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = ProductCategory::findOrFail($id);
        $this->authorize('delete', $category);
        if (! $category->canBeDeleted()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene familias asociadas',
                'userMessage' => 'No se puede eliminar la categoría porque tiene familias asociadas',
            ], 400);
        }
        $category->delete();
        return response()->json(['message' => 'Categoría de producto eliminada con éxito']);
    }

    public function destroyMultiple(DestroyMultipleProductCategoriesRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];
        $categories = ProductCategory::whereIn('id', $ids)->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($categories as $category) {
            $this->authorize('delete', $category);
            if (! $category->canBeDeleted()) {
                $errors[] = "Categoría '{$category->name}' no se puede eliminar porque tiene familias asociadas";
                continue;
            }
            $category->delete();
            $deletedCount++;
        }

        $message = "Se eliminaron {$deletedCount} categorías con éxito";
        $userMessage = $deletedCount === 0 && count($errors) > 0
            ? (count($errors) === 1 ? $errors[0] : 'No se pudieron eliminar las categorías porque tienen familias asociadas')
            : (count($errors) > 0 ? "Se eliminaron {$deletedCount} categorías. " . (count($errors) === 1 ? $errors[0] : 'Algunas no se pudieron eliminar porque tienen familias asociadas') : "Se eliminaron {$deletedCount} categorías con éxito");

        return response()->json([
            'message' => $message . (count($errors) > 0 ? '. Errores: ' . implode(', ', $errors) : ''),
            'userMessage' => $userMessage,
            'deletedCount' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewOptions', ProductCategory::class);
        $categories = ProductCategory::where('active', true)
            ->select('id', 'name', 'description')
            ->orderBy('name', 'asc')
            ->get();
        return response()->json($categories);
    }
}
