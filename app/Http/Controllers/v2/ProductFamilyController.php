<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProductFamilyResource;
use App\Models\ProductFamily;
use Illuminate\Http\Request;

class ProductFamilyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProductFamily::query();
        $query->with('category');

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('categoryId')) {
            $query->where('category_id', $request->categoryId);
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $query->orderBy('name', 'asc');

        $perPage = $request->input('perPage', 12);
        return ProductFamilyResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'categoryId' => 'required|exists:product_categories,id',
            'active' => 'boolean',
        ]);

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

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $family = ProductFamily::with(['category', 'products'])->findOrFail($id);

        return response()->json([
            'message' => 'Familia de producto obtenida con éxito',
            'data' => new ProductFamilyResource($family),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $family = ProductFamily::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'categoryId' => 'sometimes|required|exists:product_categories,id',
            'active' => 'boolean',
        ]);

        $updateData = [
            'name' => $validated['name'] ?? $family->name,
            'description' => $validated['description'] ?? $family->description,
            'active' => $validated['active'] ?? $family->active,
        ];

        if (isset($validated['categoryId'])) {
            $updateData['category_id'] = $validated['categoryId'];
        }

        $family->update($updateData);
        $family->load('category');

        return response()->json([
            'message' => 'Familia de producto actualizada con éxito',
            'data' => new ProductFamilyResource($family),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $family = ProductFamily::findOrFail($id);
        
        // Verificar si tiene productos asociados
        if ($family->products()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la familia porque tiene productos asociados',
            ], 400);
        }

        $family->delete();

        return response()->json([
            'message' => 'Familia de producto eliminada con éxito',
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
                'message' => 'No se han proporcionado IDs válidos',
            ], 400);
        }

        $families = ProductFamily::whereIn('id', $ids)->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($families as $family) {
            // Verificar si tiene productos asociados
            if ($family->products()->count() > 0) {
                $errors[] = "Familia '{$family->name}' no se puede eliminar porque tiene productos asociados";
                continue;
            }

            $family->delete();
            $deletedCount++;
        }

        $message = "Se eliminaron {$deletedCount} familias con éxito";
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
     * Get all options for the product families select box.
     */
    public function options()
    {
        $families = ProductFamily::where('active', true)
            ->with('category:id,name')
            ->select('id', 'name', 'description', 'category_id')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($family) {
                return [
                    'id' => $family->id,
                    'name' => $family->name,
                    'description' => $family->description,
                    'categoryId' => $family->category_id,
                    'categoryName' => $family->category->name ?? null,
                ];
            });

        return response()->json($families);
    }
}
