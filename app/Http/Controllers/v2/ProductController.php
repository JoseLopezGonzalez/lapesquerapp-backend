<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleProductsRequest;
use App\Http\Requests\v2\StoreProductRequest;
use App\Http\Requests\v2\UpdateProductRequest;
use App\Http\Resources\v2\ProductResource;
use App\Models\Product;
use App\Services\v2\ProductListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Products
 * Gestión de productos (familias, especies, zonas de captura).
 */
class ProductController extends Controller
{
    /**
     * Listar productos
     *
     * @queryParam id integer ID del producto.
     * @queryParam ids array Lista de IDs.
     * @queryParam name string Filtrar por nombre (parcial).
     * @queryParam species array IDs de especies.
     * @queryParam categories array IDs de categorías.
     * @queryParam families array IDs de familias.
     * @queryParam perPage integer Elementos por página. Example: 14
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);
        return ProductResource::collection(ProductListService::list($request));
    }

    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();
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

    public function show(string $id)
    {
        $product = Product::with(['species.fishingGear', 'captureZone', 'family.category', 'family'])->findOrFail($id);
        $this->authorize('view', $product);

        return response()->json([
            'message' => 'Producto obtenido con éxito',
            'data' => new ProductResource($product),
        ]);
    }

    public function update(UpdateProductRequest $request, string $id)
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);

        DB::transaction(fn () => $product->update($request->getUpdateData()));

        $updated = Product::with(['species.fishingGear', 'captureZone', 'family.category', 'family'])->find($id);

        return response()->json([
            'message' => 'Producto actualizado con éxito',
            'data' => new ProductResource($updated),
        ]);
    }

    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $this->authorize('delete', $product);

        if ($product->isInUse()) {
            $blocked = $product->deletionBlockedBy();
            $reasons = array_filter([
                $blocked['boxes'] ? 'cajas' : null,
                $blocked['orders'] ? 'pedidos' : null,
                $blocked['production'] ? 'producción' : null,
            ]);
            $reasonsText = implode(', ', $reasons);

            return response()->json([
                'message' => 'No se puede eliminar el producto porque está en uso',
                'details' => 'El producto está siendo utilizado en: ' . $reasonsText,
                'userMessage' => 'No se puede eliminar el producto porque está siendo utilizado en: ' . $reasonsText,
            ], 400);
        }

        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    public function destroyMultiple(DestroyMultipleProductsRequest $request)
    {
        $ids = $request->validated()['ids'];
        $products = Product::whereIn('id', $ids)->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($products as $product) {
            $this->authorize('delete', $product);

            if ($product->isInUse()) {
                $blocked = $product->deletionBlockedBy();
                $reasons = array_filter([
                    $blocked['boxes'] ? 'cajas' : null,
                    $blocked['orders'] ? 'pedidos' : null,
                    $blocked['production'] ? 'producción' : null,
                ]);
                $reasonsText = implode(', ', $reasons);
                $errors[] = "Producto '{$product->name}' no se puede eliminar porque está siendo utilizado en: {$reasonsText}";
                continue;
            }

            $product->delete();
            $deletedCount++;
        }

        $message = "Se eliminaron {$deletedCount} productos con éxito";
        $userMessage = '';

        if (! empty($errors)) {
            $message .= '. Errores: ' . implode(', ', $errors);
            if ($deletedCount === 0) {
                $userMessage = count($errors) === 1
                    ? $errors[0]
                    : 'No se pudieron eliminar los productos porque están siendo utilizados en cajas, pedidos o producción';
            } else {
                $userMessage = count($errors) === 1
                    ? "Se eliminaron {$deletedCount} productos. {$errors[0]}"
                    : "Se eliminaron {$deletedCount} productos. Algunos no se pudieron eliminar porque están siendo utilizados en cajas, pedidos o producción";
            }
        } else {
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
     * Opciones para select (id, name, boxGtin).
     */
    public function options()
    {
        $this->authorize('viewOptions', Product::class);
        $products = Product::select('id', 'name', 'box_gtin as boxGtin')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($products);
    }
}
