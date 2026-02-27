<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\v2\DestroyMultipleCeboDispatchesRequest;
use App\Http\Requests\v2\IndexCeboDispatchRequest;
use App\Http\Requests\v2\StoreCeboDispatchRequest;
use App\Http\Requests\v2\UpdateCeboDispatchRequest;
use App\Http\Resources\v2\CeboDispatchResource;
use App\Models\CeboDispatch;
use App\Models\CeboDispatchProduct;
use App\Models\Supplier;
use App\Services\v2\CeboDispatchListService;
use Illuminate\Support\Facades\DB;

use function normalizeDateToBusiness;

class CeboDispatchController extends Controller
{
    /**
     * Para cada product_id, devuelve el precio de la última línea (por fecha/id de despacho) del mismo
     * proveedor que contenga ese producto con price no nulo. Así cada línea puede tomar el precio de
     * la salida anterior que sí incluyera ese producto, no solo de "la última salida en general".
     *
     * @param  int  $supplierId
     * @param  int[]  $productIds  IDs de producto a consultar
     * @param  int|null  $excludeDispatchId  Excluir este despacho (p. ej. el que se está editando en update)
     * @return array<int, float> product_id => price
     */
    private function getLastPricePerProduct(int $supplierId, array $productIds, ?int $excludeDispatchId = null): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }

        $lines = CeboDispatchProduct::query()
            ->select('cebo_dispatch_products.product_id', 'cebo_dispatch_products.price')
            ->join('cebo_dispatches', 'cebo_dispatches.id', '=', 'cebo_dispatch_products.dispatch_id')
            ->where('cebo_dispatches.supplier_id', $supplierId)
            ->whereIn('cebo_dispatch_products.product_id', $productIds)
            ->whereNotNull('cebo_dispatch_products.price')
            ->when($excludeDispatchId !== null, fn ($q) => $q->where('cebo_dispatches.id', '!=', $excludeDispatchId))
            ->orderByDesc('cebo_dispatches.date')
            ->orderByDesc('cebo_dispatches.id')
            ->get();

        $prices = [];
        foreach ($lines as $line) {
            $pid = (int) $line->product_id;
            if (! array_key_exists($pid, $prices)) {
                $prices[$pid] = (float) $line->price;
            }
        }

        return $prices;
    }

    public function index(IndexCeboDispatchRequest $request)
    {
        return CeboDispatchResource::collection(CeboDispatchListService::list($request));
    }

    public function store(StoreCeboDispatchRequest $request)
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $supplierId = (int) $validated['supplier']['id'];
            $exportType = $validated['export_type'] ?? $validated['exportType'] ?? null;
            if ($exportType === null) {
                $supplier = Supplier::find($supplierId);
                $exportType = $supplier && in_array($supplier->cebo_export_type, ['a3erp', 'facilcom'], true)
                    ? $supplier->cebo_export_type
                    : null;
            }

            $dispatch = new CeboDispatch;
            $dispatch->supplier_id = $supplierId;
            $dispatch->date = normalizeDateToBusiness($validated['date']);
            $dispatch->notes = $validated['notes'] ?? null;
            $dispatch->export_type = $exportType;
            $dispatch->save();

            $productIds = array_map(fn ($d) => (int) $d['product']['id'], $validated['details']);
            $lastPrices = $this->getLastPricePerProduct($supplierId, $productIds);
            foreach ($validated['details'] as $detail) {
                $productId = (int) $detail['product']['id'];
                $price = array_key_exists('price', $detail) && $detail['price'] !== null && $detail['price'] !== ''
                    ? (float) $detail['price']
                    : ($lastPrices[$productId] ?? null);
                $dispatch->products()->create([
                    'product_id' => $productId,
                    'net_weight' => (float) $detail['netWeight'],
                    'price' => $price,
                ]);
            }

            $dispatch->load('supplier', 'products.product');

            return response()->json([
                'message' => 'Despacho de cebo creado correctamente.',
                'data' => new CeboDispatchResource($dispatch),
            ], 201);
        });
    }

    public function show($id)
    {
        $dispatch = CeboDispatch::with('supplier', 'products.product')->findOrFail($id);
        $this->authorize('view', $dispatch);

        return response()->json([
            'data' => new CeboDispatchResource($dispatch),
        ]);
    }

    public function update(UpdateCeboDispatchRequest $request, $id)
    {
        $validated = $request->validated();
        $dispatch = CeboDispatch::findOrFail($id);
        $this->authorize('update', $dispatch);

        return DB::transaction(function () use ($dispatch, $validated) {
            $supplierId = (int) $validated['supplier']['id'];
            $exportType = $validated['export_type'] ?? $validated['exportType'] ?? null;
            if ($exportType === null) {
                $supplier = Supplier::find($supplierId);
                $exportType = $supplier && in_array($supplier->cebo_export_type, ['a3erp', 'facilcom'], true)
                    ? $supplier->cebo_export_type
                    : $dispatch->export_type;
            }
            $dispatch->update([
                'supplier_id' => $supplierId,
                'date' => normalizeDateToBusiness($validated['date']),
                'notes' => $validated['notes'] ?? null,
                'export_type' => $exportType,
            ]);

            $productIds = array_map(fn ($d) => (int) $d['product']['id'], $validated['details']);
            $lastPrices = $this->getLastPricePerProduct($supplierId, $productIds, $dispatch->id);
            $dispatch->products()->delete();
            foreach ($validated['details'] as $detail) {
                $productId = (int) $detail['product']['id'];
                $price = array_key_exists('price', $detail) && $detail['price'] !== null && $detail['price'] !== ''
                    ? (float) $detail['price']
                    : ($lastPrices[$productId] ?? null);
                $dispatch->products()->create([
                    'product_id' => $productId,
                    'net_weight' => (float) $detail['netWeight'],
                    'price' => $price,
                ]);
            }

            $dispatch->load('supplier', 'products.product');

            return response()->json([
                'message' => 'Despacho de cebo actualizado correctamente.',
                'data' => new CeboDispatchResource($dispatch),
            ]);
        });
    }

    public function destroy($id)
    {
        $dispatch = CeboDispatch::findOrFail($id);
        $this->authorize('delete', $dispatch);

        // Eliminar el despacho - las líneas (cebo_dispatch_products) se eliminarán automáticamente
        // por cascade, pero los productos en sí NO se eliminarán
        $dispatch->delete();

        return response()->json(['message' => 'Despacho de cebo eliminado correctamente'], 200);
    }

    public function destroyMultiple(DestroyMultipleCeboDispatchesRequest $request)
    {
        $ids = $request->validated('ids');
        $dispatches = CeboDispatch::whereIn('id', $ids)->get();

        foreach ($dispatches as $dispatch) {
            $this->authorize('delete', $dispatch);
        }

        CeboDispatch::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Despachos de cebo eliminados correctamente']);
    }
}
