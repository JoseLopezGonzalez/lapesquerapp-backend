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

        $items = [];
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

            $items[] = [
                'type' => 'reception',
                'id' => $reception->id,
                'date' => $reception->date,
                'reception' => [
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
                ],
                'related_dispatches' => $relatedDispatches->map(function($dispatch) {
                    return [
                        'id' => $dispatch->id,
                        'date' => $dispatch->date,
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
                        'total_amount' => round($dispatch->products->sum(function($product) {
                            return ($product->net_weight ?? 0) * ($product->price ?? 0);
                        }), 2),
                    ];
                })->values(),
            ];
        }

        // Agregar salidas sin recepción relacionada
        foreach ($dispatches as $dispatch) {
            if (!in_array($dispatch->id, $processedDispatchIds)) {
                $items[] = [
                    'type' => 'dispatch',
                    'id' => $dispatch->id,
                    'date' => $dispatch->date,
                    'dispatch' => [
                        'id' => $dispatch->id,
                        'date' => $dispatch->date,
                        'notes' => $dispatch->notes,
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
                        'total_amount' => round($dispatch->products->sum(function($product) {
                            return ($product->net_weight ?? 0) * ($product->price ?? 0);
                        }), 2),
                    ],
                    'related_reception' => null,
                ];
            }
        }

        // Ordenar items por fecha
        usort($items, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Calcular resumen
        $totalReceptions = $receptions->count();
        $totalDispatches = $dispatches->count();
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
        $totalDeclaredWeight = $receptions->sum('declared_total_net_weight');
        $totalDeclaredAmount = $receptions->sum('declared_total_amount');
        $netAmount = $totalReceptionsAmount - $totalDispatchesAmount;

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
            'items' => $items,
            'summary' => [
                'total_receptions' => $totalReceptions,
                'total_dispatches' => $totalDispatches,
                'total_receptions_weight' => round($totalReceptionsWeight, 2),
                'total_dispatches_weight' => round($totalDispatchesWeight, 2),
                'total_receptions_amount' => round($totalReceptionsAmount, 2),
                'total_dispatches_amount' => round($totalDispatchesAmount, 2),
                'total_declared_weight' => round($totalDeclaredWeight, 2),
                'total_declared_amount' => round($totalDeclaredAmount, 2),
                'net_amount' => round($netAmount, 2),
            ],
        ]);
    }

    /**
     * Generar PDF de liquidación
     * 
     * GET /v2/supplier-liquidations/{supplierId}/pdf?dates[start]=2024-01-01&dates[end]=2024-01-31
     */
    public function generatePdf(Request $request, $supplierId)
    {
        $request->validate([
            'dates.start' => 'required|date',
            'dates.end' => 'required|date|after_or_equal:dates.start',
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
            'items' => $details['items'],
            'summary' => $details['summary'],
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
}

