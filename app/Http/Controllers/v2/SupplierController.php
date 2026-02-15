<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleSuppliersRequest;
use App\Http\Requests\v2\IndexSupplierRequest;
use App\Http\Requests\v2\StoreSupplierRequest;
use App\Http\Requests\v2\UpdateSupplierRequest;
use App\Http\Resources\v2\SupplierResource;
use App\Models\Supplier;
use App\Services\v2\SupplierListService;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function index(IndexSupplierRequest $request)
    {
        $this->authorize('viewAny', Supplier::class);

        return SupplierResource::collection(SupplierListService::list($request));
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $validated = $request->validated();
        $validated = $this->prepareEmailsForStorage($validated);

        $supplier = Supplier::create($validated);

        return response()->json([
            'message' => 'Proveedor creado correctamente.',
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorize('view', $supplier);

        return response()->json([
            'message' => 'Proveedor obtenido con éxito',
            'data' => new SupplierResource($supplier),
        ]);
    }

    public function update(UpdateSupplierRequest $request, string $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorize('update', $supplier);

        $validated = $request->validated();
        $validated = $this->prepareEmailsForStorage($validated);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Proveedor actualizado correctamente.',
            'data' => new SupplierResource($supplier),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return response()->json(['message' => 'Proveedor eliminado con éxito']);
    }

    public function destroyMultiple(DestroyMultipleSuppliersRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $ids = $request->validated('ids');
        $suppliers = Supplier::whereIn('id', $ids)->get();

        foreach ($suppliers as $supplier) {
            $this->authorize('delete', $supplier);
        }

        Supplier::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Proveedores eliminados con éxito']);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = Supplier::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($suppliers);
    }

    /**
     * Convierte arrays emails/ccEmails a string para almacenamiento.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function prepareEmailsForStorage(array $validated): array
    {
        $allEmails = [];

        foreach ($validated['emails'] ?? [] as $email) {
            $allEmails[] = trim((string) $email);
        }

        foreach ($validated['ccEmails'] ?? [] as $email) {
            $allEmails[] = 'CC:'.trim((string) $email);
        }

        $validated['emails'] = count($allEmails) > 0
            ? implode(";\n", $allEmails).';'
            : null;

        unset($validated['ccEmails']);

        return $validated;
    }
}
