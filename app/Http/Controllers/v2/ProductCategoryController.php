<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProductCategory::query();

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 12);
        return ProductCategoryResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'active' => 'boolean',
        ]);

        $category = ProductCategory::create($validated);

        return response()->json([
            'message' => 'Categoría de producto creada con éxito',
            'data' => new ProductCategoryResource($category),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = ProductCategory::with('families')->findOrFail($id);

        return response()->json([
            'message' => 'Categoría de producto obtenida con éxito',
            'data' => new ProductCategoryResource($category),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $category = ProductCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'active' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Categoría de producto actualizada con éxito',
            'data' => new ProductCategoryResource($category),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = ProductCategory::findOrFail($id);
        
        // Verificar si tiene familias asociadas
        if ($category->families()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene familias asociadas',
            ], 400);
        }

        $category->delete();

        return response()->json([
            'message' => 'Categoría de producto eliminada con éxito',
        ]);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json([
                'message' => 'No se han proporcionado IDs válidos.',
                'userMessage' => 'Debe proporcionar al menos un ID válido para eliminar.'
            ], 400);
        }

        $categories = ProductCategory::whereIn('id', $ids)->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($categories as $category) {
            // Verificar si tiene familias asociadas
            if ($category->families()->count() > 0) {
                $errors[] = "Categoría '{$category->name}' no se puede eliminar porque tiene familias asociadas";
                continue;
            }

            $category->delete();
            $deletedCount++;
        }

        $message = "Se eliminaron {$deletedCount} categorías con éxito";
        if (!empty($errors)) {
            $message .= ". Errores: " . implode(', ', $errors);
        }

        return response()->json([
            'message' => $message,
            'deletedCount' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Get all options for the product categories select box.
     */
    public function options()
    {
        $categories = ProductCategory::where('active', true)
            ->select('id', 'name', 'description')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($categories);
    }
}
