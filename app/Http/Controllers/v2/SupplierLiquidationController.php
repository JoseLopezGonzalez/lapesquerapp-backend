<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\RawMaterialReception;
use App\Models\CeboDispatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Beganovich\Snappdf\Snappdf;

class SupplierLiquidationController extends Controller
{
    /**
     * Listar proveedores con actividad (recepciones o salidas de cebo) en un rango de fechas
     * 
     * GET /v2/supplier-liquidations/suppliers?dates[start]=2024-01-01&dates[end]=2024-01-31
     */
    public function getSuppliers(Request $request)
    {
        $request->validate([
            'dates.start' => 'required|date',
            'dates.end' => 'required|date|after_or_equal:dates.start',
        ]);

        $dates = $request->input('dates', []);
        $startDate = isset($dates['start']) ? date('Y-m-d 00:00:00', strtotime($dates['start'])) : null;
        $endDate = isset($dates['end']) ? date('Y-m-d 23:59:59', strtotime($dates['end'])) : null;

        // Obtener proveedores con recepciones en el rango
        $suppliersWithReceptions = Supplier::whereHas('rawMaterialReceptions', function($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })->get();

        // Obtener proveedores con salidas de cebo en el rango
        $suppliersWithDispatches = Supplier::whereHas('ceboDispatches', function($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })->get();

        // Unir y eliminar duplicados
        $allSuppliers = $suppliersWithReceptions->merge($suppliersWithDispatches)->unique('id');

        // Calcular estadísticas para cada proveedor
        $result = $allSuppliers->map(function($supplier) use ($startDate, $endDate) {
            $receptions = RawMaterialReception::where('supplier_id', $supplier->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->with('products')
                ->get();

            $dispatches = CeboDispatch::where('supplier_id', $supplier->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->with('products')
                ->get();

            $totalReceptionsWeight = $receptions->sum(function($reception) {
                return $reception->products->sum(function($product) {
                    return $product->net_weight ?? 0;
                });
            });

            $totalDispatchesWeight = $dispatches->sum(function($dispatch) {
                return $dispatch->products->sum(function($product) {
                    return $product->net_weight ?? 0;
                });
            });

            $totalReceptionsAmount = $receptions->sum(function($reception) {
                return $reception->products->sum(function($product) {
                    return ($product->net_weight ?? 0) * ($product->price ?? 0);
                });
            });

            $totalDispatchesAmount = $dispatches->sum(function($dispatch) {
                return $dispatch->products->sum(function($product) {
                    return ($product->net_weight ?? 0) * ($product->price ?? 0);
                });
            });

            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'receptions_count' => $receptions->count(),
                'dispatches_count' => $dispatches->count(),
                'total_receptions_weight' => round($totalReceptionsWeight, 2),
                'total_dispatches_weight' => round($totalDispatchesWeight, 2),
                'total_receptions_amount' => round($totalReceptionsAmount, 2),
                'total_dispatches_amount' => round($totalDispatchesAmount, 2),
            ];
        })->values();

        return response()->json(['data' => $result]);
    }

    /**
     * Obtener liquidación detallada de un proveedor
     * 
     * GET /v2/supplier-liquidations/{supplierId}/details?dates[start]=2024-01-01&dates[end]=2024-01-31
     */
    public function getDetails(Request $request, $supplierId)
    {
        $request->validate([
            'dates.start' => 'required|date',
            'dates.end' => 'required|date|after_or_equal:dates.start',
        ]);

        $supplier = Supplier::findOrFail($supplierId);
        $dates = $request->input('dates', []);
        $startDate = isset($dates['start']) ? date('Y-m-d 00:00:00', strtotime($dates['start'])) : null;
        $endDate = isset($dates['end']) ? date('Y-m-d 23:59:59', strtotime($dates['end'])) : null;

        // Obtener recepciones
        $receptions = RawMaterialReception::where('supplier_id', $supplierId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['products.product.article', 'supplier'])
            ->orderBy('date', 'asc')
            ->get();

        // Obtener salidas de cebo
        $dispatches = CeboDispatch::where('supplier_id', $supplierId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['products.product.article', 'supplier'])
            ->orderBy('date', 'asc')
            ->get();

        $receptionsData = [];
        $dispatchesData = [];
        $processedDispatchIds = [];

        // Procesar recepciones y agrupar salidas relacionadas
        foreach ($receptions as $reception) {
            // Buscar salidas relacionadas (mismo proveedor, ±7 días)
            $relatedDispatches = $dispatches->filter(function($dispatch) use ($reception, &$processedDispatchIds) {
                $daysDiff = abs(strtotime($dispatch->date) - strtotime($reception->date)) / (60 * 60 * 24);
                return $daysDiff <= 7 && !in_array($dispatch->id, $processedDispatchIds);
            });

            // Marcar salidas como procesadas
            foreach ($relatedDispatches as $dispatch) {
                $processedDispatchIds[] = $dispatch->id;
            }

            // Calcular totales de la recepción
            $calculatedTotalWeight = $reception->products->sum(function($product) {
                return $product->net_weight ?? 0;
            });
            $calculatedTotalAmount = $reception->products->sum(function($product) {
                return ($product->net_weight ?? 0) * ($product->price ?? 0);
            });
            $averagePrice = $calculatedTotalWeight > 0 ? $calculatedTotalAmount / $calculatedTotalWeight : 0;

            $receptionsData[] = [
                'id' => $reception->id,
                'date' => $reception->date,
                'notes' => $reception->notes,
                'products' => $reception->products->map(function($product) {
                    $productModel = $product->product;
                    return [
                        'id' => $product->id,
                        'product' => [
                            'id' => $productModel->id ?? null,
                            'name' => $productModel->name ?? ($productModel->article->name ?? null),
                            'code' => $productModel->a3erp_code ?? $productModel->facil_com_code ?? null,
                        ],
                        'lot' => $product->lot ?? null,
                        'net_weight' => round($product->net_weight ?? 0, 2),
                        'price' => round($product->price ?? 0, 2),
                        'amount' => round(($product->net_weight ?? 0) * ($product->price ?? 0), 2),
                        'boxes' => $product->boxes ?? 0,
                    ];
                })->values(),
                'declared_total_net_weight' => round($reception->declared_total_net_weight ?? 0, 2),
                'declared_total_amount' => round($reception->declared_total_amount ?? 0, 2),
                'calculated_total_net_weight' => round($calculatedTotalWeight, 2),
                'calculated_total_amount' => round($calculatedTotalAmount, 2),
                'average_price' => round($averagePrice, 2),
                'related_dispatches' => $relatedDispatches->map(function($dispatch) {
                    $exportType = $dispatch->export_type ?? 'facilcom';
                    $ivaRate = ($exportType === 'a3erp') ? 0.10 : 0.00; // 10% para a3erp, 0% para facilcom
                    
                    $baseAmount = round($dispatch->products->sum(function($product) {
                        return ($product->net_weight ?? 0) * ($product->price ?? 0);
                    }), 2);
                    
                    $ivaAmount = round($baseAmount * $ivaRate, 2);
                    $totalAmount = round($baseAmount + $ivaAmount, 2);
                    
                    return [
                        'id' => $dispatch->id,
                        'date' => $dispatch->date,
                        'notes' => $dispatch->notes,
                        'export_type' => $exportType,
                        'iva_rate' => $ivaRate * 100, // Porcentaje para mostrar
                        'products' => $dispatch->products->map(function($product) {
                            $productModel = $product->product;
                            return [
                                'id' => $product->id,
                                'product' => [
                                    'id' => $productModel->id ?? null,
                                    'name' => $productModel->name ?? ($productModel->article->name ?? null),
                                    'code' => $productModel->a3erp_code ?? $productModel->facil_com_code ?? null,
                                ],
                                'net_weight' => round($product->net_weight ?? 0, 2),
                                'price' => round($product->price ?? 0, 2),
                                'amount' => round(($product->net_weight ?? 0) * ($product->price ?? 0), 2),
                            ];
                        })->values(),
                        'total_net_weight' => round($dispatch->products->sum(function($product) {
                            return $product->net_weight ?? 0;
                        }), 2),
                        'base_amount' => $baseAmount,
                        'iva_amount' => $ivaAmount,
                        'total_amount' => $totalAmount,
                    ];
                })->values(),
            ];
        }

        // Agregar salidas sin recepción relacionada
        foreach ($dispatches as $dispatch) {
            if (!in_array($dispatch->id, $processedDispatchIds)) {
                $exportType = $dispatch->export_type ?? 'facilcom';
                $ivaRate = ($exportType === 'a3erp') ? 0.10 : 0.00; // 10% para a3erp, 0% para facilcom
                
                $baseAmount = round($dispatch->products->sum(function($product) {
                    return ($product->net_weight ?? 0) * ($product->price ?? 0);
                }), 2);
                
                $ivaAmount = round($baseAmount * $ivaRate, 2);
                $totalAmount = round($baseAmount + $ivaAmount, 2);
                
                $dispatchesData[] = [
                    'id' => $dispatch->id,
                    'date' => $dispatch->date,
                    'notes' => $dispatch->notes,
                    'export_type' => $exportType,
                    'iva_rate' => $ivaRate * 100, // Porcentaje para mostrar
                    'products' => $dispatch->products->map(function($product) {
                        $productModel = $product->product;
                        return [
                            'id' => $product->id,
                            'product' => [
                                'id' => $productModel->id ?? null,
                                'name' => $productModel->name ?? ($productModel->article->name ?? null),
                                'code' => $productModel->a3erp_code ?? $productModel->facil_com_code ?? null,
                            ],
                            'net_weight' => round($product->net_weight ?? 0, 2),
                            'price' => round($product->price ?? 0, 2),
                            'amount' => round(($product->net_weight ?? 0) * ($product->price ?? 0), 2),
                        ];
                    })->values(),
                    'total_net_weight' => round($dispatch->products->sum(function($product) {
                        return $product->net_weight ?? 0;
                    }), 2),
                    'base_amount' => $baseAmount,
                    'iva_amount' => $ivaAmount,
                    'total_amount' => $totalAmount,
                ];
            }
        }

        // Ordenar por fecha
        usort($receptionsData, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        usort($dispatchesData, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Calcular resumen
        $totalReceptions = $receptions->count();
        $totalDispatches = $dispatches->count();
        
        // Totales calculados (reales) de recepciones
        $totalCalculatedWeight = $receptions->sum(function($reception) {
            return $reception->products->sum(function($product) {
                return $product->net_weight ?? 0;
            });
        });
        $totalCalculatedAmount = $receptions->sum(function($reception) {
            return $reception->products->sum(function($product) {
                return ($product->net_weight ?? 0) * ($product->price ?? 0);
            });
        });
        
        // Totales de salidas de cebo
        $totalDispatchesWeight = $dispatches->sum(function($dispatch) {
            return $dispatch->products->sum(function($product) {
                return $product->net_weight ?? 0;
            });
        });
        $totalDispatchesAmount = $dispatches->sum(function($dispatch) {
            return $dispatch->products->sum(function($product) {
                return ($product->net_weight ?? 0) * ($product->price ?? 0);
            });
        });
        
        // Totales declarados
        $totalDeclaredWeight = $receptions->sum('declared_total_net_weight');
        $totalDeclaredAmount = $receptions->sum('declared_total_amount');
        
        // Diferencias (calculado - declarado)
        $weightDifference = $totalCalculatedWeight - $totalDeclaredWeight;
        $amountDifference = $totalCalculatedAmount - $totalDeclaredAmount;
        
        // Importe neto total (diferencia entre calculado y declarado)
        $netAmount = $amountDifference;
        
        // Calcular totales de cebo con IVA
        $totalDispatchesBaseAmount = 0;
        $totalDispatchesIvaAmount = 0;
        $totalDispatchesAmount = 0;
        
        // Sumar salidas independientes
        foreach ($dispatchesData as $dispatch) {
            $totalDispatchesBaseAmount += $dispatch['base_amount'] ?? 0;
            $totalDispatchesIvaAmount += $dispatch['iva_amount'] ?? 0;
            $totalDispatchesAmount += $dispatch['total_amount'] ?? 0;
        }
        
        // Sumar salidas relacionadas
        foreach ($receptionsData as $reception) {
            if (!empty($reception['related_dispatches'])) {
                foreach ($reception['related_dispatches'] as $dispatch) {
                    $totalDispatchesBaseAmount += $dispatch['base_amount'] ?? 0;
                    $totalDispatchesIvaAmount += $dispatch['iva_amount'] ?? 0;
                    $totalDispatchesAmount += $dispatch['total_amount'] ?? 0;
                }
            }
        }
        
        // Verificar si hay IVA en las salidas de cebo
        $hasIvaInDispatches = $totalDispatchesIvaAmount > 0;
        
        // Calcular Total Declarado con IVA (asumiendo 10% de IVA sobre el declarado)
        $totalDeclaredWithIva = round($totalDeclaredAmount * 1.10, 2);

        return response()->json([
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_person' => $supplier->contact_person,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
            ],
            'date_range' => [
                'start' => $dates['start'] ?? null,
                'end' => $dates['end'] ?? null,
            ],
            'receptions' => $receptionsData,
            'dispatches' => $dispatchesData,
            'summary' => [
                'total_receptions' => $totalReceptions,
                'total_dispatches' => $totalDispatches,
                'total_receptions_weight' => round($totalCalculatedWeight, 2),
                'total_receptions_amount' => round($totalCalculatedAmount, 2),
                'total_dispatches_weight' => round($totalDispatchesWeight, 2),
                'total_dispatches_base_amount' => round($totalDispatchesBaseAmount, 2),
                'total_dispatches_iva_amount' => round($totalDispatchesIvaAmount, 2),
                'total_dispatches_amount' => round($totalDispatchesAmount, 2),
                'total_declared_weight' => round($totalDeclaredWeight, 2),
                'total_declared_amount' => round($totalDeclaredAmount, 2),
                'total_declared_with_iva' => $totalDeclaredWithIva,
                'weight_difference' => round($weightDifference, 2),
                'amount_difference' => round($amountDifference, 2),
                'net_amount' => round($netAmount, 2),
                'has_iva_in_dispatches' => $hasIvaInDispatches,
            ],
        ]);
    }

    /**
     * Generar PDF de liquidación
     * 
     * GET /v2/supplier-liquidations/{supplierId}/pdf?dates[start]=2024-01-01&dates[end]=2024-01-31&receptions[]=1&receptions[]=2&dispatches[]=3
     */
    public function generatePdf(Request $request, $supplierId)
    {
        $request->validate([
            'dates.start' => 'required|date',
            'dates.end' => 'required|date|after_or_equal:dates.start',
            'receptions' => 'nullable|array',
            'receptions.*' => 'integer|exists:tenant.raw_material_receptions,id',
            'dispatches' => 'nullable|array',
            'dispatches.*' => 'integer|exists:tenant.cebo_dispatches,id',
            'payment_method' => 'nullable|in:cash,transfer',
            'has_management_fee' => 'nullable|boolean',
        ]);

        // Obtener los datos de la liquidación (reutiliza la lógica de getDetails)
        $detailsResponse = $this->getDetails($request, $supplierId);
        
        // Verificar si hay errores en la respuesta
        if ($detailsResponse->getStatusCode() !== 200) {
            return $detailsResponse;
        }
        
        $details = json_decode($detailsResponse->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Error al procesar los datos de liquidación'], 500);
        }

        // Filtrar recepciones y salidas según selección del usuario
        $selectedReceptionIds = $request->input('receptions', []);
        $selectedDispatchIds = $request->input('dispatches', []);
        
        // Normalizar arrays: si viene como string vacío o null, convertir a array vacío
        if (!is_array($selectedReceptionIds)) {
            $selectedReceptionIds = [];
        }
        if (!is_array($selectedDispatchIds)) {
            $selectedDispatchIds = [];
        }
        
        // Convertir IDs a enteros para comparación correcta
        $selectedReceptionIds = array_map('intval', $selectedReceptionIds);
        $selectedDispatchIds = array_map('intval', $selectedDispatchIds);
        
        // Inicializar con todos los datos
        $filteredReceptions = $details['receptions'] ?? [];
        $filteredDispatches = $details['dispatches'] ?? [];
        
        // Si se especificaron recepciones (y el array no está vacío), filtrar solo las seleccionadas
        if (!empty($selectedReceptionIds) && count($selectedReceptionIds) > 0) {
            $filteredReceptions = array_filter($details['receptions'] ?? [], function($reception) use ($selectedReceptionIds) {
                return in_array((int)$reception['id'], $selectedReceptionIds, true);
            });
            $filteredReceptions = array_values($filteredReceptions); // Reindexar array
        }
        
        // IMPORTANTE: Si NO se especifican salidas (dispatches[]), se incluyen TODAS las salidas independientes
        // Solo filtrar si el usuario especificó explícitamente qué salidas quiere
        if (!empty($selectedDispatchIds) && count($selectedDispatchIds) > 0) {
            // Filtrar salidas independientes según selección
            $filteredDispatches = array_filter($details['dispatches'] ?? [], function($dispatch) use ($selectedDispatchIds) {
                return in_array((int)$dispatch['id'], $selectedDispatchIds, true);
            });
            $filteredDispatches = array_values($filteredDispatches); // Reindexar array
            
            // También filtrar las salidas relacionadas dentro de las recepciones
            // Si una recepción tiene dispatches relacionados seleccionados, mantenerlos
            foreach ($filteredReceptions as &$reception) {
                if (!empty($reception['related_dispatches'])) {
                    $reception['related_dispatches'] = array_filter($reception['related_dispatches'], function($dispatch) use ($selectedDispatchIds) {
                        return in_array((int)$dispatch['id'], $selectedDispatchIds, true);
                    });
                    $reception['related_dispatches'] = array_values($reception['related_dispatches']);
                }
            }
            unset($reception);
            
            // IMPORTANTE: Si hay dispatches seleccionados que están en recepciones NO seleccionadas,
            // debemos agregarlos al array de dispatches independientes para que aparezcan en el PDF
            // Recopilar todos los dispatches seleccionados que no están en recepciones seleccionadas
            $selectedDispatchesFromUnselectedReceptions = [];
            foreach ($details['receptions'] ?? [] as $reception) {
                // Solo procesar recepciones que NO están seleccionadas
                if (!in_array((int)$reception['id'], $selectedReceptionIds, true)) {
                    if (!empty($reception['related_dispatches'])) {
                        foreach ($reception['related_dispatches'] as $dispatch) {
                            // Si este dispatch está seleccionado, agregarlo
                            if (in_array((int)$dispatch['id'], $selectedDispatchIds, true)) {
                                $selectedDispatchesFromUnselectedReceptions[] = $dispatch;
                            }
                        }
                    }
                }
            }
            
            // Agregar estos dispatches al array de dispatches filtrados
            if (!empty($selectedDispatchesFromUnselectedReceptions)) {
                $filteredDispatches = array_merge($filteredDispatches, $selectedDispatchesFromUnselectedReceptions);
            }
        }
        // Si NO se especificaron salidas, $filteredDispatches mantiene TODAS las salidas independientes
        // que vienen de $details['dispatches'] (no necesita filtrado adicional)
        
        // Recalcular resumen con los datos filtrados
        $summary = $this->calculateSummary($filteredReceptions, $filteredDispatches);
        
        // Calcular totales de pago según la lógica compleja
        $paymentTotals = $this->calculatePaymentTotals($summary, $request);
        
        $dates = $request->input('dates', []);
        $startDate = $dates['start'] ?? null;
        $endDate = $dates['end'] ?? null;
        
        $supplierName = $details['supplier']['name'] ?? 'Proveedor';
        $fileName = 'Liquidacion_Proveedor_' . str_replace([' ', '/', '\\'], '_', $supplierName) . '_' . $startDate . '_' . $endDate;

        // Generar PDF usando Snappdf
        $snappdf = new Snappdf();
        $html = view('pdf.v2.supplier_liquidations.liquidation', [
            'supplier' => $details['supplier'],
            'date_range' => $details['date_range'],
            'receptions' => $filteredReceptions,
            'dispatches' => $filteredDispatches,
            'summary' => $summary,
            'payment_totals' => $paymentTotals,
        ])->render();
        
        $snappdf->setChromiumPath('/usr/bin/google-chrome');

        // Configuración de márgenes
        $snappdf->addChromiumArguments('--margin-top=10mm');
        $snappdf->addChromiumArguments('--margin-right=30mm');
        $snappdf->addChromiumArguments('--margin-bottom=10mm');
        $snappdf->addChromiumArguments('--margin-left=10mm');

        // Argumentos de optimización y compatibilidad
        $chromiumArgs = [
            '--no-sandbox',
            'disable-gpu',
            'disable-translate',
            'disable-extensions',
            'disable-sync',
            'disable-background-networking',
            'disable-software-rasterizer',
            'disable-default-apps',
            'disable-dev-shm-usage',
            'safebrowsing-disable-auto-update',
            'run-all-compositor-stages-before-draw',
            'no-first-run',
            'no-margins',
            'print-to-pdf-no-header',
            'no-pdf-header-footer',
            'hide-scrollbars',
            'ignore-certificate-errors'
        ];

        foreach ($chromiumArgs as $arg) {
            $snappdf->addChromiumArguments($arg);
        }

        // Generar PDF
        $pdf = $snappdf->setHtml($html)->generate();

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, "{$fileName}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * Calcular resumen de liquidación con recepciones y salidas filtradas
     */
    private function calculateSummary(array $receptions, array $dispatches)
    {
        $totalReceptions = count($receptions);
        
        // Contar todas las salidas: las independientes + las relacionadas dentro de recepciones
        $allDispatchIds = [];
        foreach ($dispatches as $dispatch) {
            $allDispatchIds[] = $dispatch['id'];
        }
        foreach ($receptions as $reception) {
            if (!empty($reception['related_dispatches'])) {
                foreach ($reception['related_dispatches'] as $dispatch) {
                    if (!in_array($dispatch['id'], $allDispatchIds)) {
                        $allDispatchIds[] = $dispatch['id'];
                    }
                }
            }
        }
        $totalDispatches = count($allDispatchIds);
        
        // Totales calculados (reales) de recepciones filtradas
        $totalCalculatedWeight = array_sum(array_column($receptions, 'calculated_total_net_weight'));
        $totalCalculatedAmount = array_sum(array_column($receptions, 'calculated_total_amount'));
        
        // Totales de salidas de cebo: independientes + relacionadas (con IVA)
        $totalDispatchesWeight = array_sum(array_column($dispatches, 'total_net_weight'));
        $totalDispatchesBaseAmount = array_sum(array_column($dispatches, 'base_amount'));
        $totalDispatchesIvaAmount = array_sum(array_column($dispatches, 'iva_amount'));
        $totalDispatchesAmount = array_sum(array_column($dispatches, 'total_amount'));
        
        // Sumar salidas relacionadas dentro de las recepciones
        foreach ($receptions as $reception) {
            if (!empty($reception['related_dispatches'])) {
                foreach ($reception['related_dispatches'] as $dispatch) {
                    $totalDispatchesWeight += $dispatch['total_net_weight'] ?? 0;
                    $totalDispatchesBaseAmount += $dispatch['base_amount'] ?? 0;
                    $totalDispatchesIvaAmount += $dispatch['iva_amount'] ?? 0;
                    $totalDispatchesAmount += $dispatch['total_amount'] ?? 0;
                }
            }
        }
        
        // Totales declarados de recepciones filtradas
        $totalDeclaredWeight = array_sum(array_column($receptions, 'declared_total_net_weight'));
        $totalDeclaredAmount = array_sum(array_column($receptions, 'declared_total_amount'));
        
        // Diferencias (calculado - declarado)
        $weightDifference = $totalCalculatedWeight - $totalDeclaredWeight;
        $amountDifference = $totalCalculatedAmount - $totalDeclaredAmount;
        
        // Importe neto total (diferencia entre calculado y declarado)
        $netAmount = $amountDifference;
        
        // Verificar si hay IVA en las salidas de cebo
        $hasIvaInDispatches = $totalDispatchesIvaAmount > 0;
        
        // Calcular Total Declarado con IVA (asumiendo 10% de IVA sobre el declarado)
        $totalDeclaredWithIva = round($totalDeclaredAmount * 1.10, 2);
        
        return [
            'total_receptions' => $totalReceptions,
            'total_dispatches' => $totalDispatches,
            'total_receptions_weight' => round($totalCalculatedWeight, 2),
            'total_receptions_amount' => round($totalCalculatedAmount, 2),
            'total_dispatches_weight' => round($totalDispatchesWeight, 2),
            'total_dispatches_base_amount' => round($totalDispatchesBaseAmount, 2),
            'total_dispatches_iva_amount' => round($totalDispatchesIvaAmount, 2),
            'total_dispatches_amount' => round($totalDispatchesAmount, 2),
            'total_declared_weight' => round($totalDeclaredWeight, 2),
            'total_declared_amount' => round($totalDeclaredAmount, 2),
            'total_declared_with_iva' => $totalDeclaredWithIva,
            'weight_difference' => round($weightDifference, 2),
            'amount_difference' => round($amountDifference, 2),
            'net_amount' => round($netAmount, 2),
            'has_iva_in_dispatches' => $hasIvaInDispatches,
        ];
    }

    /**
     * Calcular totales de pago según la lógica compleja de efectivo/transferencia y gasto de gestión
     */
    private function calculatePaymentTotals(array $summary, Request $request)
    {
        $paymentMethod = $request->input('payment_method'); // 'cash' o 'transfer'
        $hasManagementFee = $request->boolean('has_management_fee', false);
        
        $hasIvaInDispatches = $summary['has_iva_in_dispatches'] ?? false;
        
        $result = [
            'has_iva_in_dispatches' => $hasIvaInDispatches,
            'payment_method' => $paymentMethod,
            'has_management_fee' => $hasManagementFee,
            'total_cash' => null,
            'total_transfer' => null,
            'management_fee' => null,
            'total_transfer_final' => null,
        ];
        
        // Solo calcular si hay IVA en dispatches
        if (!$hasIvaInDispatches) {
            return $result;
        }
        
        // Variables base
        $totalReception = $summary['total_receptions_amount'] ?? 0; // Sin IVA
        $totalDeclared = $summary['total_declared_amount'] ?? 0; // Sin IVA
        $totalDeclaredWithIva = $summary['total_declared_with_iva'] ?? 0; // Con IVA (declarado * 1.10)
        $totalDispatchesAmount = $summary['total_dispatches_amount'] ?? 0; // Con IVA
        
        // Calcular Total Efectivo (si payment_method = 'cash')
        if ($paymentMethod === 'cash') {
            // Total Efectivo = Total Recepción (sin IVA) - Total Declarado (sin IVA) - Total Salida Cebo (con IVA)
            $result['total_cash'] = round($totalReception - $totalDeclared - $totalDispatchesAmount, 2);
        }
        
        // Calcular Total Transferencia (si payment_method = 'transfer')
        if ($paymentMethod === 'transfer') {
            // Total Transferencia = Total Declarado (con IVA) - Total Salida Cebo (con IVA)
            $result['total_transfer'] = round($totalDeclaredWithIva - $totalDispatchesAmount, 2);
            
            // Calcular Gasto de Gestión (si aplica)
            if ($hasManagementFee) {
                // Gasto de Gestión = Total Declarado (sin IVA) * 0.025 (2.5%)
                $result['management_fee'] = round($totalDeclared * 0.025, 2);
                
                // Total Transferencia Final = Total Transferencia - Gasto de Gestión
                $result['total_transfer_final'] = round($result['total_transfer'] - $result['management_fee'], 2);
            } else {
                $result['total_transfer_final'] = $result['total_transfer'];
            }
        }
        
        return $result;
    }
}

