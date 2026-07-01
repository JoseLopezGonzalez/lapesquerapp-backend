<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleAuxiliaryProductsRequest;
use App\Http\Requests\v2\StoreAuxiliaryProductRequest;
use App\Http\Requests\v2\UpdateAuxiliaryProductRequest;
use App\Http\Resources\v2\AuxiliaryProductResource;
use App\Models\AuxiliaryProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuxiliaryProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuxiliaryProduct::class);

        $query = AuxiliaryProduct::query();

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }
        if ($request->has('ids')) {
            $query->whereIn('id', $request->ids);
        }
        if ($request->has('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }
        if ($request->has('reference')) {
            $query->where('reference', 'like', '%'.$request->reference.'%');
        }
        if ($request->filled('active')) {
            $query->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('name', 'asc');
        $perPage = $request->input('perPage', 12);

        return AuxiliaryProductResource::collection($query->paginate($perPage))->response();
    }

    public function store(StoreAuxiliaryProductRequest $request): JsonResponse
    {
        $auxiliaryProduct = AuxiliaryProduct::create($request->validated());

        return response()->json([
            'message' => 'Artículo auxiliar creado correctamente.',
            'data' => new AuxiliaryProductResource($auxiliaryProduct),
        ], 201);
    }

    public function show(AuxiliaryProduct $auxiliaryProduct): JsonResponse
    {
        $this->authorize('view', $auxiliaryProduct);

        return response()->json([
            'message' => 'Artículo auxiliar obtenido con éxito.',
            'data' => new AuxiliaryProductResource($auxiliaryProduct),
        ]);
    }

    public function update(UpdateAuxiliaryProductRequest $request, AuxiliaryProduct $auxiliaryProduct): JsonResponse
    {
        $auxiliaryProduct->update($request->validated());

        return response()->json([
            'message' => 'Artículo auxiliar actualizado con éxito.',
            'data' => new AuxiliaryProductResource($auxiliaryProduct),
        ]);
    }

    public function destroy(AuxiliaryProduct $auxiliaryProduct): JsonResponse
    {
        $this->authorize('delete', $auxiliaryProduct);

        if ($auxiliaryProduct->isInUse()) {
            return response()->json([
                'message' => 'No se puede eliminar el artículo auxiliar porque está en uso',
                'details' => 'El artículo auxiliar está siendo utilizado en líneas de pedido.',
                'userMessage' => 'No se puede eliminar el artículo auxiliar porque está siendo utilizado en pedidos.',
            ], 400);
        }

        $auxiliaryProduct->delete();

        return response()->json(['message' => 'Artículo auxiliar eliminado con éxito.']);
    }

    public function destroyMultiple(DestroyMultipleAuxiliaryProductsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $auxiliaryProducts = AuxiliaryProduct::whereIn('id', $validated['ids'])->get();

        $inUse = [];
        foreach ($auxiliaryProducts as $auxiliaryProduct) {
            if ($auxiliaryProduct->isInUse()) {
                $inUse[] = ['id' => $auxiliaryProduct->id, 'name' => $auxiliaryProduct->name];
            }
        }

        if (! empty($inUse)) {
            return response()->json([
                'message' => 'No se pueden eliminar algunos artículos auxiliares porque están en uso',
                'details' => 'En uso: '.implode(', ', array_column($inUse, 'name')),
                'userMessage' => 'No se pueden eliminar algunos artículos auxiliares porque están en uso: '.implode(', ', array_column($inUse, 'name')),
            ], 400);
        }

        AuxiliaryProduct::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => 'Artículos auxiliares eliminados con éxito.']);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewOptions', AuxiliaryProduct::class);

        $auxiliaryProducts = AuxiliaryProduct::select('id', 'name', 'reference', 'unit', 'default_price')
            ->where('active', true)
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn (AuxiliaryProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'reference' => $product->reference,
                'unit' => $product->unit,
                'defaultPrice' => $product->default_price,
            ]);

        return response()->json($auxiliaryProducts);
    }
}
