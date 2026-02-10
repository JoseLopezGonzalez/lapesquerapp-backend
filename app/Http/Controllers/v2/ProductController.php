<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\v2\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query();
        /* Add family and species relations (species.fishingGear for toArrayAssoc) */
        $query->with(['family.category', 'family', 'species.fishingGear', 'captureZone']);

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }

        /* Filter by name */
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        /* species Where in*/
        if ($request->has('species')) {
            $query->whereIn('species_id', $request->species);
        }

        /* capture zone where in*/
        if ($request->has('captureZones')) {
            $query->whereIn('capture_zone_id', $request->captureZones);
        }

        /* category where in */
        if ($request->has('categories')) {
            $query->whereHas('family', function($query) use ($request) {
                $query->whereIn('category_id', $request->categories);
            });
        }

        /* family where in */
        if ($request->has('families')) {
            $query->whereIn('family_id', $request->families);
        }

        /* articleGtin */
        if ($request->has('articleGtin')) {
            $query->where('article_gtin', $request->articleGtin);
        }

        /* boxGtin */
        if ($request->has('boxGtin')) {
            $query->where('box_gtin', $request->boxGtin);
        }

        /* palletGtin */
        if ($request->has('palletGtin')) {
            $query->where('pallet_gtin', $request->palletGtin);
        }


        /* Always order by name */
        $query->orderBy('name', 'asc');


        $perPage = $request->input('perPage', 14); // Default a 10 si no se proporciona
        return ProductResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        // Normalize snake_case to camelCase for backward compatibility
        $requestData = $request->all();
        if (isset($requestData['species_id']) && !isset($requestData['speciesId'])) {
            $requestData['speciesId'] = $requestData['species_id'];
        }
        if (isset($requestData['capture_zone_id']) && !isset($requestData['captureZoneId'])) {
            $requestData['captureZoneId'] = $requestData['capture_zone_id'];
        }
        if (isset($requestData['family_id']) && !isset($requestData['familyId'])) {
            $requestData['familyId'] = $requestData['family_id'];
        }
        if (isset($requestData['article_gtin']) && !isset($requestData['articleGtin'])) {
            $requestData['articleGtin'] = $requestData['article_gtin'];
        }
        if (isset($requestData['box_gtin']) && !isset($requestData['boxGtin'])) {
            $requestData['boxGtin'] = $requestData['box_gtin'];
        }
        if (isset($requestData['pallet_gtin']) && !isset($requestData['palletGtin'])) {
            $requestData['palletGtin'] = $requestData['pallet_gtin'];
        }
        
        // Convert empty strings to null for GTINs to allow null values
        if (isset($requestData['articleGtin']) && $requestData['articleGtin'] === '') {
            $requestData['articleGtin'] = null;
        }
        if (isset($requestData['boxGtin']) && $requestData['boxGtin'] === '') {
            $requestData['boxGtin'] = null;
        }
        if (isset($requestData['palletGtin']) && $requestData['palletGtin'] === '') {
            $requestData['palletGtin'] = null;
        }
        
        $validator = Validator::make($requestData, [
            'name' => 'required|string|min:3|max:255|unique:tenant.products,name',
            'speciesId' => 'required|exists:tenant.species,id',
            'captureZoneId' => 'required|exists:tenant.capture_zones,id',
            'familyId' => 'nullable|exists:tenant.product_families,id',
            'articleGtin' => 'nullable|string|regex:/^[0-9]{8,14}$/|max:14|unique:tenant.products,article_gtin',
            'boxGtin' => 'nullable|string|regex:/^[0-9]{8,14}$/|max:14|unique:tenant.products,box_gtin',
            'palletGtin' => 'nullable|string|regex:/^[0-9]{8,14}$/|max:14|unique:tenant.products,pallet_gtin',
            'a3erp_code' => 'nullable|string|max:255',
            'facil_com_code' => 'nullable|string|max:255',
        ], [
            'name.unique' => 'Ya existe un producto con este nombre.',
            'articleGtin.regex' => 'El GTIN del artículo debe tener entre 8 y 14 dígitos numéricos.',
            'articleGtin.unique' => 'Ya existe un producto con este GTIN de artículo.',
            'boxGtin.regex' => 'El GTIN de la caja debe tener entre 8 y 14 dígitos numéricos.',
            'boxGtin.unique' => 'Ya existe un producto con este GTIN de caja.',
            'palletGtin.regex' => 'El GTIN del palet debe tener entre 8 y 14 dígitos numéricos.',
            'palletGtin.unique' => 'Ya existe un producto con este GTIN de palet.',
        ]);
        
        if ($validator->fails()) {
            throw \Illuminate\Validation\ValidationException::withMessages($validator->errors()->toArray());
        }
        
        $validated = $validator->validated();

        $productId = null;

        DB::transaction(function () use (&$productId, $validated) {
            $product = Product::create([
                'name' => $validated['name'],
                'species_id' => $validated['speciesId'],
                'capture_zone_id' => $validated['captureZoneId'],
                'family_id' => $validated['familyId'] ?? null,
                'article_gtin' => $validated['articleGtin'] ?? null,
                'box_gtin' => $validated['boxGtin'] ?? null,
                'pallet_gtin' => $validated['palletGtin'] ?? null,
                'a3erp_code' => $validated['a3erp_code'] ?? null,
                'facil_com_code' => $validated['facil_com_code'] ?? null,
            ]);
            $productId = $product->id;
        });

        $product = Product::with(['species.fishingGear', 'captureZone', 'family.category'])->find($productId);

        return response()->json([
            'message' => 'Producto creado con éxito',
            'data' => new ProductResource($product),
        ], 201);
    }








    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with(['species.fishingGear', 'captureZone', 'family.category', 'family'])->findOrFail($id);

        return response()->json([
            'message' => 'Producto obtenido con éxito',
            'data' => new ProductResource($product),
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        // Normalize snake_case to camelCase for backward compatibility
        $requestData = $request->all();
        if (isset($requestData['species_id']) && !isset($requestData['speciesId'])) {
            $requestData['speciesId'] = $requestData['species_id'];
        }
        if (isset($requestData['capture_zone_id']) && !isset($requestData['captureZoneId'])) {
            $requestData['captureZoneId'] = $requestData['capture_zone_id'];
        }
        if (isset($requestData['family_id']) && !isset($requestData['familyId'])) {
            $requestData['familyId'] = $requestData['family_id'];
        }
        if (isset($requestData['article_gtin']) && !isset($requestData['articleGtin'])) {
            $requestData['articleGtin'] = $requestData['article_gtin'];
        }
        if (isset($requestData['box_gtin']) && !isset($requestData['boxGtin'])) {
            $requestData['boxGtin'] = $requestData['box_gtin'];
        }
        if (isset($requestData['pallet_gtin']) && !isset($requestData['palletGtin'])) {
            $requestData['palletGtin'] = $requestData['pallet_gtin'];
        }
        
        // Convert empty strings to null for GTINs to allow null values
        if (isset($requestData['articleGtin']) && $requestData['articleGtin'] === '') {
            $requestData['articleGtin'] = null;
        }
        if (isset($requestData['boxGtin']) && $requestData['boxGtin'] === '') {
            $requestData['boxGtin'] = null;
        }
        if (isset($requestData['palletGtin']) && $requestData['palletGtin'] === '') {
            $requestData['palletGtin'] = null;
        }
        
        $validator = Validator::make($requestData, [
            'name' => 'sometimes|required|string|min:3|max:255|unique:tenant.products,name,' . $id,
            'speciesId' => 'sometimes|required|exists:tenant.species,id',
            'captureZoneId' => 'sometimes|required|exists:tenant.capture_zones,id',
            'familyId' => 'nullable|exists:tenant.product_families,id',
            'articleGtin' => 'nullable|string|regex:/^[0-9]{8,14}$/|max:14|unique:tenant.products,article_gtin,' . $id,
            'boxGtin' => 'nullable|string|regex:/^[0-9]{8,14}$/|max:14|unique:tenant.products,box_gtin,' . $id,
            'palletGtin' => 'nullable|string|regex:/^[0-9]{8,14}$/|max:14|unique:tenant.products,pallet_gtin,' . $id,
            'a3erp_code' => 'nullable|string|max:255',
            'facil_com_code' => 'nullable|string|max:255',
        ], [
            'name.unique' => 'Ya existe un producto con este nombre.',
            'articleGtin.regex' => 'El GTIN del artículo debe tener entre 8 y 14 dígitos numéricos.',
            'articleGtin.unique' => 'Ya existe un producto con este GTIN de artículo.',
            'boxGtin.regex' => 'El GTIN de la caja debe tener entre 8 y 14 dígitos numéricos.',
            'boxGtin.unique' => 'Ya existe un producto con este GTIN de caja.',
            'palletGtin.regex' => 'El GTIN del palet debe tener entre 8 y 14 dígitos numéricos.',
            'palletGtin.unique' => 'Ya existe un producto con este GTIN de palet.',
        ]);
        
        if ($validator->fails()) {
            throw \Illuminate\Validation\ValidationException::withMessages($validator->errors()->toArray());
        }
        
        $validated = $validator->validated();

        DB::transaction(function () use ($product, $validated) {
            $updateData = [];
            
            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }
            
            if (isset($validated['speciesId'])) {
                $updateData['species_id'] = $validated['speciesId'];
            }
            
            if (isset($validated['captureZoneId'])) {
                $updateData['capture_zone_id'] = $validated['captureZoneId'];
            }
            
            if (isset($validated['familyId'])) {
                $updateData['family_id'] = $validated['familyId'];
            }
            
            if (isset($validated['articleGtin'])) {
                $updateData['article_gtin'] = $validated['articleGtin'];
            }
            
            if (isset($validated['boxGtin'])) {
                $updateData['box_gtin'] = $validated['boxGtin'];
            }
            
            if (isset($validated['palletGtin'])) {
                $updateData['pallet_gtin'] = $validated['palletGtin'];
            }
            
            if (isset($validated['a3erp_code'])) {
                $updateData['a3erp_code'] = $validated['a3erp_code'];
            }
            
            if (isset($validated['facil_com_code'])) {
                $updateData['facil_com_code'] = $validated['facil_com_code'];
            }
            
            $product->update($updateData);
        });

        $updated = Product::with(['species.fishingGear', 'captureZone', 'family.category', 'family'])->find($id);

        return response()->json([
            'message' => 'Producto actualizado con éxito',
            'data' => new ProductResource($updated),
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);

        // Validar si el producto está en uso antes de eliminar
        $usedInBoxes = \App\Models\Box::where('article_id', $id)->exists();
        $usedInOrders = \App\Models\OrderPlannedProductDetail::where('product_id', $id)->exists();
        $usedInProduction = \App\Models\ProductionOutput::where('product_id', $id)->exists();

        if ($usedInBoxes || $usedInOrders || $usedInProduction) {
            $reasons = [];
            if ($usedInBoxes) $reasons[] = 'cajas';
            if ($usedInOrders) $reasons[] = 'pedidos';
            if ($usedInProduction) $reasons[] = 'producción';
            
            $reasonsText = implode(', ', $reasons);
            
            return response()->json([
                'message' => 'No se puede eliminar el producto porque está en uso',
                'details' => 'El producto está siendo utilizado en: ' . $reasonsText,
                'userMessage' => 'No se puede eliminar el producto porque está siendo utilizado en: ' . $reasonsText
            ], 400);
        }

        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json([
                'message' => 'No se proporcionaron IDs válidos.',
                'userMessage' => 'Debe proporcionar al menos un ID válido para eliminar.'
            ], 400);
        }

        $products = Product::whereIn('id', $ids)->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($products as $product) {
            // Validar si el producto está en uso antes de eliminar
            $usedInBoxes = \App\Models\Box::where('article_id', $product->id)->exists();
            $usedInOrders = \App\Models\OrderPlannedProductDetail::where('product_id', $product->id)->exists();
            $usedInProduction = \App\Models\ProductionOutput::where('product_id', $product->id)->exists();

            if ($usedInBoxes || $usedInOrders || $usedInProduction) {
                $reasons = [];
                if ($usedInBoxes) $reasons[] = 'cajas';
                if ($usedInOrders) $reasons[] = 'pedidos';
                if ($usedInProduction) $reasons[] = 'producción';
                
                $reasonsText = implode(', ', $reasons);
                $errors[] = "Producto '{$product->name}' no se puede eliminar porque está siendo utilizado en: {$reasonsText}";
                continue;
            }

            $product->delete();
            $deletedCount++;
        }

        // Construir mensajes en lenguaje natural
        $message = "Se eliminaron {$deletedCount} productos con éxito";
        $userMessage = '';
        
        if (!empty($errors)) {
            $message .= ". Errores: " . implode(', ', $errors);
            
            // Generar mensaje en lenguaje natural para el usuario
            if ($deletedCount === 0) {
                // No se eliminó ninguno
                if (count($errors) === 1) {
                    $userMessage = $errors[0];
                } else {
                    $userMessage = 'No se pudieron eliminar los productos porque están siendo utilizados en cajas, pedidos o producción';
                }
            } else {
                // Se eliminaron algunos pero no todos
                if (count($errors) === 1) {
                    $userMessage = "Se eliminaron {$deletedCount} productos. {$errors[0]}";
                } else {
                    $userMessage = "Se eliminaron {$deletedCount} productos. Algunos no se pudieron eliminar porque están siendo utilizados en cajas, pedidos o producción";
                }
            }
        } else {
            // Todos se eliminaron exitosamente
            $userMessage = "Se eliminaron {$deletedCount} productos con éxito";
        }

        return response()->json([
            'message' => $message,
            'userMessage' => $userMessage,
            'deletedCount' => $deletedCount,
            'errors' => $errors,
        ]);
    }



    /**
     * Get all options for the products select box.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options()
    {
        $products = Product::select('id', 'name', 'box_gtin as boxGtin')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($products);
    }
}
