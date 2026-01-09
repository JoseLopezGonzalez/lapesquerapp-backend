<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\v1\RawMaterialReceptionResource;
use App\Models\RawMaterial;
use App\Models\RawMaterialReception;
use App\Models\RawMaterialReceptionProduct;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RawMaterialReceptionController extends Controller
{
    public function index(Request $request)
    {
        $query = RawMaterialReception::query();
        $query->with('supplier', 'products.product');

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('suppliers')) {
            $query->whereIn('supplier_id', $request->suppliers);
        }

        if ($request->has('dates')) {
            $query->whereBetween('date', [$request->dates['start'], $request->dates['end']]);
        }

        if ($request->has('species')) {
            $query->whereHas('products.product', function ($query) use ($request) {
                $query->whereIn('species_id', $request->species);
            });
        }

        if ($request->has('products')) {
            $query->whereHas('products.product', function ($query) use ($request) {
                $query->whereIn('id', $request->products);
            });
        }

        if ($request->has('notes')) {
            $query->where('notes', 'like', '%' . $request->notes . '%');
        }

        /* Order by Date Descen */
        $query->orderBy('date', 'desc');

        $perPage = $request->input('perPage', 12); // Default a 10 si no se proporciona
        return RawMaterialReceptionResource::collection($query->paginate($perPage));
    }

    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'supplier.id' => 'required',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'details' => 'required|array',
            'details.*.product.id' => 'required|exists:products,id',
            'details.*.netWeight' => 'required|numeric',
            'details.*.price' => 'nullable|numeric|min:0',
            'declaredTotalAmount' => 'nullable|numeric|min:0',
            'declaredTotalNetWeight' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // Código de estado 422 - Unprocessable Entity
        }

        $reception = new RawMaterialReception();
        $reception->supplier_id = $request->supplier['id'];
        $reception->date = $request->date;

        if ($request->has('declaredTotalAmount')) {
            $reception->declared_total_amount = $request->declaredTotalAmount;
        }

        if ($request->has('declaredTotalNetWeight')) {
            $reception->declared_total_net_weight = $request->declaredTotalNetWeight;
        }

        if ($request->has('notes')) {
            $reception->notes = $request->notes;
        }

        $reception->save();

        if ($request->has('details')) {
            /* foreach ($request->details as $detail) {
                $reception->products()->create([
                    'product_id' => $detail['product']['id'],
                    'net_weight' => $detail['netWeight'],
                    'price' => $detail['price'] ?? null
                ]);
            } */
            if ($request->has('details')) {
                foreach ($request->details as $detail) {
                    $productId = $detail['product']['id'];
                    $netWeight = $detail['netWeight'];
                    $price = $detail['price'] ?? null;

                    // Si no viene el precio, lo buscamos
                    if (is_null($price)) {
                        $price = RawMaterialReceptionProduct::where('product_id', $productId)
                            ->whereHas('reception', function ($query) use ($request) {
                                $query->where('supplier_id', $request->supplier['id']);
                            })
                            ->latest('created_at')
                            ->value('price');
                    }

                    $reception->products()->create([
                        'product_id' => $productId,
                        'net_weight' => $netWeight,
                        'price' => $price,
                    ]);
                }
            }

        }

        $reception->save();


        return new RawMaterialReceptionResource($reception);
    }

    public function show($id)
    {
        $reception = RawMaterialReception::with('supplier', 'products.product')->findOrFail($id);
        return new RawMaterialReceptionResource($reception);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'supplier.id' => 'required',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'details' => 'required|array',
            'details.*.product.id' => 'required|exists:products,id',
            'details.*.netWeight' => 'required|numeric',
            'details.*.price' => 'nullable|numeric|min:0',
            'declaredTotalAmount' => 'nullable|numeric|min:0',
            'declaredTotalNetWeight' => 'nullable|numeric|min:0'
        ]);

        $reception = RawMaterialReception::findOrFail($id);
        $reception->update([
            'supplier_id' => $validated['supplier']['id'],
            'date' => $validated['date'],
            'notes' => $validated['notes'],
            'declared_total_amount' => $request->declaredTotalAmount ?? null,
            'declared_total_net_weight' => $request->declaredTotalNetWeight ?? null,
        ]);

        $reception->products()->delete();
        /* foreach ($validated['details'] as $detail) {
            $reception->products()->create([
                'product_id' => $detail['product']['id'],
                'net_weight' => $detail['netWeight'],
                'price' => $detail['price'] ?? null
            ]);
        } */
        foreach ($validated['details'] as $detail) {
            $productId = $detail['product']['id'];
            $netWeight = $detail['netWeight'];
            $price = $detail['price'] ?? null;

            if (is_null($price)) {
                $price = RawMaterialReceptionProduct::where('product_id', $productId)
                    ->whereHas('reception', function ($query) use ($validated) {
                        $query->where('supplier_id', $validated['supplier']['id']);
                    })
                    ->latest('created_at')
                    ->value('price');
            }

            $reception->products()->create([
                'product_id' => $productId,
                'net_weight' => $netWeight,
                'price' => $price,
            ]);
        }

        return new RawMaterialReceptionResource($reception);



    }

    public function destroy($id)
    {

        $order = RawMaterialReception::findOrFail($id);
        $order->delete();
        return response()->json(['message' => 'Palet eliminado correctamente'], 200);
    }

    public function updateDeclaredData(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:tenant.suppliers,id',
            'date' => 'required|date',
            'declared_total_amount' => 'nullable|numeric|min:0',
            'declared_total_net_weight' => 'nullable|numeric|min:0',
        ]);

        // Buscar la recepción
        $reception = RawMaterialReception::where('supplier_id', $validated['supplier_id'])
            ->whereDate('date', $validated['date'])
            ->first();

        if (!$reception) {
            // Verificar si existe alguna recepción para este proveedor
            $receptionCount = RawMaterialReception::where('supplier_id', $validated['supplier_id'])->count();
            
            // Buscar la recepción más reciente para este proveedor
            $latestReception = RawMaterialReception::where('supplier_id', $validated['supplier_id'])
                ->orderBy('date', 'desc')
                ->first();

            $errorDetails = [
                'error' => 'Reception not found',
                'message' => 'No se encontró una recepción para el proveedor y fecha especificados.',
                'search_criteria' => [
                    'supplier_id' => $validated['supplier_id'],
                    'date' => $validated['date'],
                ],
            ];

            if ($receptionCount > 0) {
                $errorDetails['hint'] = "Existen {$receptionCount} recepción(es) para este proveedor, pero ninguna en la fecha especificada.";
                if ($latestReception) {
                    $errorDetails['latest_reception_date'] = Carbon::parse($latestReception->date)->format('Y-m-d');
                }
            } else {
                $errorDetails['hint'] = 'No existen recepciones para este proveedor.';
            }

            return response()->json($errorDetails, 404);
        }

        // Actualizar los valores
        $reception->update([
            'declared_total_amount' => $validated['declared_total_amount'],
            'declared_total_net_weight' => $validated['declared_total_net_weight'],
        ]);

        return new RawMaterialReceptionResource($reception);
    }

    /**
     * Actualizar datos declarados de múltiples recepciones de forma masiva
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateDeclaredData(Request $request)
    {
        $validated = $request->validate([
            'receptions' => 'required|array|min:1',
            'receptions.*.supplier_id' => 'required|integer|exists:tenant.suppliers,id',
            'receptions.*.date' => 'required|date',
            'receptions.*.declared_total_amount' => 'nullable|numeric|min:0',
            'receptions.*.declared_total_net_weight' => 'nullable|numeric|min:0',
        ]);

        $results = [];
        $errors = [];

        foreach ($validated['receptions'] as $receptionData) {
            $supplierId = $receptionData['supplier_id'];
            $date = $receptionData['date'];

            try {
                // Buscar la recepción
                $reception = RawMaterialReception::where('supplier_id', $supplierId)
                    ->whereDate('date', $date)
                    ->first();

                if (!$reception) {
                    // Verificar si existe alguna recepción para este proveedor
                    $receptionCount = RawMaterialReception::where('supplier_id', $supplierId)->count();
                    
                    // Buscar la recepción más reciente para este proveedor
                    $latestReception = RawMaterialReception::where('supplier_id', $supplierId)
                        ->orderBy('date', 'desc')
                        ->first();

                    $errorDetails = [
                        'supplier_id' => $supplierId,
                        'date' => $date,
                        'error' => 'Reception not found',
                        'message' => 'No se encontró una recepción para el proveedor y fecha especificados.',
                        'search_criteria' => [
                            'supplier_id' => $supplierId,
                            'date' => $date,
                        ],
                    ];

                    if ($receptionCount > 0) {
                        $errorDetails['hint'] = "Existen {$receptionCount} recepción(es) para este proveedor, pero ninguna en la fecha especificada.";
                        if ($latestReception) {
                            $errorDetails['latest_reception_date'] = Carbon::parse($latestReception->date)->format('Y-m-d');
                        }
                    } else {
                        $errorDetails['hint'] = 'No existen recepciones para este proveedor.';
                    }

                    $errors[] = $errorDetails;
                    continue;
                }

                // Actualizar los valores
                $reception->update([
                    'declared_total_amount' => $receptionData['declared_total_amount'] ?? $reception->declared_total_amount,
                    'declared_total_net_weight' => $receptionData['declared_total_net_weight'] ?? $reception->declared_total_net_weight,
                ]);

                $results[] = [
                    'supplier_id' => $supplierId,
                    'date' => $date,
                    'reception_id' => $reception->id,
                    'status' => 'updated',
                    'message' => 'Recepción actualizada correctamente',
                    'updated_data' => [
                        'declared_total_amount' => $reception->declared_total_amount,
                        'declared_total_net_weight' => $reception->declared_total_net_weight,
                    ],
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'supplier_id' => $supplierId,
                    'date' => $date,
                    'error' => $e->getMessage(),
                    'message' => 'Error al actualizar la recepción',
                ];
            }
        }

        $response = [
            'message' => 'Proceso de actualización masiva completado',
            'total' => count($validated['receptions']),
            'updated' => count($results),
            'errors' => count($errors),
            'results' => $results,
        ];

        if (!empty($errors)) {
            $response['errors_details'] = $errors;
        }

        $statusCode = empty($errors) ? 200 : 207; // 207 Multi-Status si hay algunos errores
        return response()->json($response, $statusCode);
    }

}
