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
            // Buscar recepciones más cercanas
            $closestReceptions = $this->findClosestReceptions($validated['supplier_id'], $validated['date']);

            $errorDetails = [
                'error' => 'Reception not found',
                'message' => 'No se encontró una recepción para el proveedor y fecha especificados.',
                'search_criteria' => [
                    'supplier_id' => $validated['supplier_id'],
                    'date' => $validated['date'],
                ],
                'closest_receptions' => $closestReceptions,
            ];

            // Construir mensaje de ayuda con la recepción más cercana
            if ($closestReceptions['closest']) {
                $closest = $closestReceptions['closest'];
                $direction = $closest['type'] === 'previous' ? 'anterior' : 'posterior';
                $errorDetails['hint'] = "Recepción más cercana ({$direction}): {$closest['date']} (ID: {$closest['id']}, diferencia: {$closest['days_diff']} día(s))";
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
                    // Buscar recepciones más cercanas
                    $closestReceptions = $this->findClosestReceptions($supplierId, $date);

                    $errorDetails = [
                        'supplier_id' => $supplierId,
                        'date' => $date,
                        'error' => 'Reception not found',
                        'message' => 'No se encontró una recepción para el proveedor y fecha especificados.',
                        'search_criteria' => [
                            'supplier_id' => $supplierId,
                            'date' => $date,
                        ],
                        'closest_receptions' => $closestReceptions,
                    ];

                    // Construir mensaje de ayuda con la recepción más cercana
                    if ($closestReceptions['closest']) {
                        $closest = $closestReceptions['closest'];
                        $direction = $closest['type'] === 'previous' ? 'anterior' : 'posterior';
                        $errorDetails['hint'] = "Recepción más cercana ({$direction}): {$closest['date']} (ID: {$closest['id']}, diferencia: {$closest['days_diff']} día(s))";
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

    /**
     * Validar y previsualizar actualización de datos declarados de múltiples recepciones
     * Este endpoint solo valida sin hacer cambios, permitiendo al frontend mostrar preview
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateBulkUpdateDeclaredData(Request $request)
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
            $newDeclaredAmount = $receptionData['declared_total_amount'] ?? null;
            $newDeclaredWeight = $receptionData['declared_total_net_weight'] ?? null;

            try {
                // Buscar la recepción
                $reception = RawMaterialReception::where('supplier_id', $supplierId)
                    ->whereDate('date', $date)
                    ->with('supplier')
                    ->first();

                if (!$reception) {
                    // Buscar recepciones más cercanas
                    $closestReceptions = $this->findClosestReceptions($supplierId, $date);

                    $errorDetails = [
                        'supplier_id' => $supplierId,
                        'date' => $date,
                        'valid' => false,
                        'error' => 'Reception not found',
                        'message' => 'No se encontró una recepción para el proveedor y fecha especificados.',
                        'search_criteria' => [
                            'supplier_id' => $supplierId,
                            'date' => $date,
                        ],
                        'closest_receptions' => $closestReceptions,
                    ];

                    // Construir mensaje de ayuda con la recepción más cercana
                    if ($closestReceptions['closest']) {
                        $closest = $closestReceptions['closest'];
                        $direction = $closest['type'] === 'previous' ? 'anterior' : 'posterior';
                        $errorDetails['hint'] = "Recepción más cercana ({$direction}): {$closest['date']} (ID: {$closest['id']}, diferencia: {$closest['days_diff']} día(s))";
                    } else {
                        $errorDetails['hint'] = 'No existen recepciones para este proveedor.';
                    }

                    $errors[] = $errorDetails;
                    continue;
                }

                // Calcular valores que tendría después de la actualización
                $currentDeclaredAmount = $reception->declared_total_amount;
                $currentDeclaredWeight = $reception->declared_total_net_weight;
                
                $finalDeclaredAmount = $newDeclaredAmount !== null ? $newDeclaredAmount : $currentDeclaredAmount;
                $finalDeclaredWeight = $newDeclaredWeight !== null ? $newDeclaredWeight : $currentDeclaredWeight;

                // Verificar si habría cambios
                $hasChanges = ($newDeclaredAmount !== null && $currentDeclaredAmount != $newDeclaredAmount) ||
                             ($newDeclaredWeight !== null && $currentDeclaredWeight != $newDeclaredWeight);

                $results[] = [
                    'supplier_id' => $supplierId,
                    'date' => $date,
                    'reception_id' => $reception->id,
                    'valid' => true,
                    'can_update' => true,
                    'has_changes' => $hasChanges,
                    'supplier_name' => $reception->supplier->name ?? null,
                    'current_data' => [
                        'declared_total_amount' => $currentDeclaredAmount,
                        'declared_total_net_weight' => $currentDeclaredWeight,
                    ],
                    'new_data' => [
                        'declared_total_amount' => $newDeclaredAmount,
                        'declared_total_net_weight' => $newDeclaredWeight,
                    ],
                    'preview_data' => [
                        'declared_total_amount' => $finalDeclaredAmount,
                        'declared_total_net_weight' => $finalDeclaredWeight,
                    ],
                    'changes_summary' => [
                        'amount_changed' => $newDeclaredAmount !== null && $currentDeclaredAmount != $newDeclaredAmount,
                        'weight_changed' => $newDeclaredWeight !== null && $currentDeclaredWeight != $newDeclaredWeight,
                        'amount_difference' => $newDeclaredAmount !== null ? ($newDeclaredAmount - ($currentDeclaredAmount ?? 0)) : 0,
                        'weight_difference' => $newDeclaredWeight !== null ? ($newDeclaredWeight - ($currentDeclaredWeight ?? 0)) : 0,
                    ],
                    'message' => $hasChanges 
                        ? 'La recepción será actualizada correctamente' 
                        : 'La recepción ya tiene estos valores (sin cambios)',
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'supplier_id' => $supplierId,
                    'date' => $date,
                    'valid' => false,
                    'error' => $e->getMessage(),
                    'message' => 'Error al validar la recepción',
                ];
            }
        }

        $response = [
            'message' => 'Validación completada',
            'total' => count($validated['receptions']),
            'valid' => count($results),
            'invalid' => count($errors),
            'ready_to_update' => count(array_filter($results, fn($r) => $r['valid'] && $r['has_changes'])),
            'no_changes' => count(array_filter($results, fn($r) => $r['valid'] && !$r['has_changes'])),
            'results' => $results,
        ];

        if (!empty($errors)) {
            $response['errors_details'] = $errors;
        }

        // Si hay errores, devolver 207 Multi-Status, si todo está bien 200
        $statusCode = empty($errors) ? 200 : 207;
        return response()->json($response, $statusCode);
    }

    /**
     * Buscar las recepciones más cercanas (anterior y posterior) a una fecha para un proveedor
     *
     * @param int $supplierId
     * @param string $date
     * @return array
     */
    private function findClosestReceptions(int $supplierId, string $date): array
    {
        $searchDate = Carbon::parse($date);
        
        // Buscar recepción anterior más cercana (fecha <= fecha buscada)
        $previousReception = RawMaterialReception::where('supplier_id', $supplierId)
            ->whereDate('date', '<=', $searchDate)
            ->orderBy('date', 'desc')
            ->first();
        
        // Buscar recepción posterior más cercana (fecha >= fecha buscada)
        $nextReception = RawMaterialReception::where('supplier_id', $supplierId)
            ->whereDate('date', '>=', $searchDate)
            ->orderBy('date', 'asc')
            ->first();
        
        $closestReception = null;
        $closestType = null;
        
        // Determinar cuál es la más cercana
        if ($previousReception && $nextReception) {
            $prevDiff = $searchDate->diffInDays(Carbon::parse($previousReception->date));
            $nextDiff = $searchDate->diffInDays(Carbon::parse($nextReception->date));
            
            if ($prevDiff <= $nextDiff) {
                $closestReception = $previousReception;
                $closestType = 'previous';
            } else {
                $closestReception = $nextReception;
                $closestType = 'next';
            }
        } elseif ($previousReception) {
            $closestReception = $previousReception;
            $closestType = 'previous';
        } elseif ($nextReception) {
            $closestReception = $nextReception;
            $closestType = 'next';
        }
        
        return [
            'previous' => $previousReception ? [
                'id' => $previousReception->id,
                'date' => Carbon::parse($previousReception->date)->format('Y-m-d'),
                'days_diff' => $previousReception ? $searchDate->diffInDays(Carbon::parse($previousReception->date)) : null,
            ] : null,
            'next' => $nextReception ? [
                'id' => $nextReception->id,
                'date' => Carbon::parse($nextReception->date)->format('Y-m-d'),
                'days_diff' => $nextReception ? $searchDate->diffInDays(Carbon::parse($nextReception->date)) : null,
            ] : null,
            'closest' => $closestReception ? [
                'id' => $closestReception->id,
                'date' => Carbon::parse($closestReception->date)->format('Y-m-d'),
                'type' => $closestType,
                'days_diff' => $searchDate->diffInDays(Carbon::parse($closestReception->date)),
            ] : null,
        ];
    }

}
