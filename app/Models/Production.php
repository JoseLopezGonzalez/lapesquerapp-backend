<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'lot',
        'date',
        'species_id',
        'capture_zone_id',
        'notes',
        'diagram_data',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'diagram_data' => 'array', // Casteo para manipular JSON como array
        'date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /* diagram_data->totalProfit  si esque existe alguna clave */
    public function getTotalProfitAttribute()
    {
        // Decodificar `diagram_data` en caso de que sea una cadena JSON
        $diagramData = is_string($this->diagram_data) ? json_decode($this->diagram_data, true) : $this->diagram_data;

        // Verificar si `diagramData` es ahora un array y contiene la estructura que necesitamos
        return is_array($diagramData) &&
            isset($diagramData['totals']) &&
            is_array($diagramData['totals']) &&
            array_key_exists('totalProfit', $diagramData['totals'])
            ? $diagramData['totals']['totalProfit']
            : null;
    }


    public function getTotalProfitPerInputKgAttribute()
    {
        // Decodificar `diagram_data` en caso de que sea una cadena JSON
        $diagramData = is_string($this->diagram_data) ? json_decode($this->diagram_data, true) : $this->diagram_data;

        // Verificar si `diagramData` es ahora un array y contiene la estructura que necesitamos
        return is_array($diagramData) &&
            isset($diagramData['totals']) &&
            is_array($diagramData['totals']) &&
            array_key_exists('totalProfitPerInputKg', $diagramData['totals'])
            ? $diagramData['totals']['totalProfitPerInputKg']
            : null;
    }



    public function getProcessNodes()
    {
        // Decodificar diagram_data
        $diagramData = is_string($this->diagram_data) ? json_decode($this->diagram_data, true) : $this->diagram_data;
        $processNodes = $diagramData['processNodes'] ?? [];
    
        // Extraer los datos clave de cada nodo
        return collect($processNodes)->map(function ($node) {
            return [
                'node_id' => $node['id'],
                'process_name' => $node['process']['name'] ?? 'Sin nombre',
                'input_quantity' => $node['inputQuantity'] ?? 0,
                'decrease' => $node['decrease'] ?? 0, // Merma
            ];
        });
    }

    public function getFinalNodes()
    {
        $diagramData = is_string($this->diagram_data) ? json_decode($this->diagram_data, true) : $this->diagram_data;
        $finalNodes = $diagramData['finalNodes'] ?? [];
    
        return collect($finalNodes)->map(function ($node) {
            $totals = $node['production']['totals'] ?? [];
            $profits = $node['profits']['totals'] ?? [];
    
            // Detalle de productos
            $productDetails = collect($node['production']['details'] ?? [])->map(function ($detail) use ($node) {
                $salesDetails = collect($node['sales']['details'] ?? [])
                    ->firstWhere('product.id', $detail['product']['id']);

                $productionSummary = collect($node['production']['summary'] ?? [])
                    ->firstWhere('product.id', $detail['product']['id']);

                $costPerKg = $productionSummary['averageCostPerKg'] ?? 0;

                $profitsSummary = collect($node['profits']['summary'] ?? [])
                    ->firstWhere('product.id', $detail['product']['id']);

                $profitPerInputKg = $profitsSummary['profitPerInputKg'] ?? 0;
                $profitPerOutputKg = $profitsSummary['profitPerOutputKg'] ?? 0;
    
                return [
                    'product_name' => $detail['product']['name'] ?? 'Producto desconocido',

                    /* Nuevo */
                    'output_quantity' => is_numeric($detail['quantity']) ? $detail['quantity'] : 0,
                    'initial_quantity' => is_numeric($detail['initialQuantity']) ? $detail['initialQuantity'] : 0,
                    /* --- */

                    'cost_per_kg' => is_numeric($costPerKg) ? $costPerKg : 0,
                    /* Dividir cost_per_kg en costPerInputKg y costPerOutpukg */
                    'cost_per_input_kg' => is_numeric($costPerKg) ? $costPerKg : 0,
                    'cost_per_output_kg' => is_numeric($costPerKg) ? $costPerKg : 0,
                    'profit_per_output_kg' => is_numeric($profitPerOutputKg) ? $profitPerOutputKg : 0,
                    'profit_per_input_kg' => is_numeric($profitPerInputKg) ? $profitPerInputKg : 0,
                ];
            });

            return [
                'node_id' => $node['id'] ?? null,
                'process_name' => $node['process']['name'] ?? 'Sin nombre',
                'process_id' => $node['process']['id'] ?? null,
                /* Cambiar total_quantity por total_output_quantity */
                'total_output_quantity' => is_numeric($totals['quantity'] ?? null) ? $totals['quantity'] : 0,
                /* total_input_quantity nuevo */
                'total_input_quantity' => is_numeric($node['totalInitialQuantity'] ?? null) ? $node['totalInitialQuantity'] : 0,
                

                'total_profit' => is_numeric($profits['totalProfit'] ?? null) ? $profits['totalProfit'] : 0, /* Añadido Nuevo */
                'profit_per_output_kg' => is_numeric($profits['averageProfitPerKg'] ?? null) ? $profits['averageProfitPerKg'] : 0,
                'profit_per_input_kg' => is_numeric($node['averageProfitPerInputKg'] ?? null) ? $node['averageProfitPerInputKg'] : 0,
                'cost_per_output_kg' => is_numeric($totals['averageCostPerKg'] ?? null) ? $totals['averageCostPerKg'] : 0,
                'products' => $productDetails->toArray(),
            ];
        });
    }
    
    

    


    






    // Relación con el modelo Species
    public function species()
    {
        return $this->belongsTo(Species::class, 'species_id');
    }

    // Relación con el modelo CaptureZone
    public function captureZone()
    {
        return $this->belongsTo(CaptureZone::class, 'capture_zone_id');
    }

    // ============================================
    // NUEVAS RELACIONES - Estructura Actualizada
    // ============================================

    /**
     * Relación con ProductionRecord (procesos del lote)
     */
    public function records()
    {
        return $this->hasMany(ProductionRecord::class, 'production_id');
    }

    /**
     * Obtener solo los procesos raíz (sin padre)
     */
    public function rootRecords()
    {
        return $this->hasMany(ProductionRecord::class, 'production_id')
            ->whereNull('parent_record_id');
    }

    /**
     * Obtener todos los inputs del lote (a través de los records)
     */
    public function allInputs()
    {
        return ProductionInput::whereIn('production_record_id', function ($query) {
            $query->select('id')
                ->from('production_records')
                ->where('production_id', $this->id);
        });
    }

    /**
     * Obtener todos los outputs del lote (a través de los records)
     */
    public function allOutputs()
    {
        return ProductionOutput::whereIn('production_record_id', function ($query) {
            $query->select('id')
                ->from('production_records')
                ->where('production_id', $this->id);
        });
    }

    // ============================================
    // MÉTODOS DE ESTADO
    // ============================================

    /**
     * Verificar si el lote está abierto
     */
    public function isOpen()
    {
        return $this->opened_at !== null && $this->closed_at === null;
    }

    /**
     * Verificar si el lote está cerrado
     */
    public function isClosed()
    {
        return $this->closed_at !== null;
    }

    /**
     * Abrir el lote de producción
     */
    public function open()
    {
        if ($this->opened_at === null) {
            $this->update(['opened_at' => now()]);
        }
        return $this;
    }

    /**
     * Cerrar el lote de producción
     */
    public function close()
    {
        if ($this->closed_at === null) {
            $this->update(['closed_at' => now()]);
        }
        return $this;
    }

    // ============================================
    // MÉTODOS DE CÁLCULO - Nueva Estructura
    // ============================================

    /**
     * Construir el árbol completo de procesos
     */
    public function buildProcessTree()
    {
        $rootRecords = $this->rootRecords()
            ->with(['process', 'inputs.box.product', 'outputs.product'])
            ->get();

        foreach ($rootRecords as $record) {
            $record->buildTree();
        }

        return $rootRecords;
    }

    /**
     * Calcular el diagrama completo dinámicamente desde los procesos
     * Retorna estructura compatible con diagram_data antiguo
     */
    public function calculateDiagram()
    {
        // Obtener procesos raíz y construir árbol
        $rootRecords = $this->buildProcessTree();

        // Convertir cada proceso raíz a estructura del diagrama
        $processNodes = $rootRecords->map(function ($record) {
            return $record->getNodeData();
        })->toArray();

        // Calcular totales globales del lote
        $globalTotals = $this->calculateGlobalTotals();

        return [
            'processNodes' => $processNodes,
            'totals' => $globalTotals,
        ];
    }

    /**
     * Obtener el diagrama en formato JSON (compatible con frontend)
     * Si existe diagram_data antiguo, lo retorna. Si no, calcula dinámicamente.
     */
    public function getDiagramData()
    {
        // Si existe diagram_data antiguo y no hay procesos nuevos, retornarlo
        if ($this->diagram_data && $this->records()->count() === 0) {
            return $this->diagram_data;
        }

        // Calcular dinámicamente desde los procesos
        return $this->calculateDiagram();
    }

    /**
     * Calcular totales globales del lote completo
     * Incluye merma (waste) y rendimiento (yield) como en ProductionRecord
     * 
     * Lógica:
     * - Si hay pérdida (input > output): waste > 0, yield = 0
     * - Si hay ganancia (input < output): yield > 0, waste = 0
     * - Si es neutro (input = output): ambos en 0
     */
    public function calculateGlobalTotals()
    {
        $totalInputWeight = $this->total_input_weight;
        $totalOutputWeight = $this->total_output_weight;
        $difference = $totalInputWeight - $totalOutputWeight;

        // Si hay pérdida (input > output)
        if ($difference > 0) {
            $totalWaste = $difference;
            $totalWastePercentage = $totalInputWeight > 0 ? ($totalWaste / $totalInputWeight) * 100 : 0;
            $totalYield = 0;
            $totalYieldPercentage = 0;
        }
        // Si hay ganancia (input < output)
        elseif ($difference < 0) {
            $totalWaste = 0;
            $totalWastePercentage = 0;
            $totalYield = abs($difference); // output - input
            $totalYieldPercentage = $totalInputWeight > 0 ? ($totalYield / $totalInputWeight) * 100 : 0;
        }
        // Si es neutro (input = output)
        else {
            $totalWaste = 0;
            $totalWastePercentage = 0;
            $totalYield = 0;
            $totalYieldPercentage = 0;
        }

        $totalInputBoxes = $this->total_input_boxes;
        $totalOutputBoxes = $this->total_output_boxes;

        $totals = [
            'totalInputWeight' => round($totalInputWeight, 2),
            'totalOutputWeight' => round($totalOutputWeight, 2),
            'totalWaste' => round($totalWaste, 2),
            'totalWastePercentage' => round($totalWastePercentage, 2),
            'totalYield' => round($totalYield, 2),
            'totalYieldPercentage' => round($totalYieldPercentage, 2),
            'totalInputBoxes' => $totalInputBoxes,
            'totalOutputBoxes' => $totalOutputBoxes,
        ];
        
        // Calcular totales de venta y stock
        $lot = $this->lot;
        $salesData = $this->getSalesDataByProduct($lot);
        $stockData = $this->getStockDataByProduct($lot);
        
        // Calcular totales de venta
        $totalSalesWeight = 0;
        $totalSalesBoxes = 0;
        $totalSalesPallets = 0;
        
        foreach ($salesData as $productId => $orders) {
            foreach ($orders as $orderData) {
                foreach ($orderData['pallets'] as $palletData) {
                    $boxes = collect($palletData['boxes']);
                    $totalSalesBoxes += $boxes->count();
                    $totalSalesWeight += $boxes->sum('net_weight');
                    $totalSalesPallets++;
                }
            }
        }
        
        // Calcular totales de stock
        $totalStockWeight = 0;
        $totalStockBoxes = 0;
        $totalStockPallets = 0;
        
        foreach ($stockData as $productId => $stores) {
            foreach ($stores as $storeData) {
                foreach ($storeData['pallets'] as $palletData) {
                    $boxes = collect($palletData['boxes']);
                    $totalStockBoxes += $boxes->count();
                    $totalStockWeight += $boxes->sum('net_weight');
                    $totalStockPallets++;
                }
            }
        }
        
        $totals['totalSalesWeight'] = round($totalSalesWeight, 2);
        $totals['totalSalesBoxes'] = $totalSalesBoxes;
        $totals['totalSalesPallets'] = $totalSalesPallets;
        $totals['totalStockWeight'] = round($totalStockWeight, 2);
        $totals['totalStockBoxes'] = $totalStockBoxes;
        $totals['totalStockPallets'] = $totalStockPallets;
        
        return $totals;
    }

    /**
     * Obtener el peso total de entrada (suma de todas las cajas de entrada)
     */
    public function getTotalInputWeightAttribute()
    {
        return $this->allInputs()
            ->join('boxes', 'production_inputs.box_id', '=', 'boxes.id')
            ->sum('boxes.net_weight');
    }

    /**
     * Obtener el peso total de salida (suma de todos los outputs)
     */
    public function getTotalOutputWeightAttribute()
    {
        return $this->allOutputs()->sum('weight_kg');
    }

    /**
     * Obtener el número total de cajas de entrada
     */
    public function getTotalInputBoxesAttribute()
    {
        return $this->allInputs()->count();
    }

    /**
     * Obtener el número total de cajas de salida
     */
    public function getTotalOutputBoxesAttribute()
    {
        return $this->allOutputs()->sum('boxes');
    }

    /**
     * Calcular la merma total (peso entrada - peso salida)
     */
    public function getTotalWasteAttribute()
    {
        return $this->total_input_weight - $this->total_output_weight;
    }

    /**
     * Calcular el porcentaje de merma
     */
    public function getWastePercentageAttribute()
    {
        if ($this->total_input_weight > 0) {
            return ($this->total_waste / $this->total_input_weight) * 100;
        }
        return 0;
    }

    // ============================================
    // CONCILIACIÓN CON STOCK
    // ============================================

    /**
     * Obtener las cajas del lote que están en stock (en palets)
     */
    public function getStockBoxes()
    {
        return Box::where('lot', $this->lot)
            ->whereHas('palletBox')
            ->get();
    }

    /**
     * Calcular el peso total de las cajas en stock para este lote
     */
    public function getStockWeightAttribute()
    {
        return Box::where('lot', $this->lot)
            ->whereHas('palletBox')
            ->sum('net_weight');
    }

    /**
     * Calcular el número de cajas en stock para este lote
     */
    public function getStockBoxesCountAttribute()
    {
        return Box::where('lot', $this->lot)
            ->whereHas('palletBox')
            ->count();
    }

    /**
     * Realizar conciliación entre producción declarada y stock real
     * Retorna: ['status' => 'green|yellow|red', 'differences' => [...]]
     */
    public function reconcile()
    {
        $declaredBoxes = $this->total_output_boxes;
        $declaredWeight = $this->total_output_weight;
        $stockBoxes = $this->stock_boxes_count;
        $stockWeight = $this->stock_weight;

        $boxDifference = abs($declaredBoxes - $stockBoxes);
        $weightDifference = abs($declaredWeight - $stockWeight);

        $boxPercentage = $declaredBoxes > 0 ? ($boxDifference / $declaredBoxes) * 100 : 0;
        $weightPercentage = $declaredWeight > 0 ? ($weightDifference / $declaredWeight) * 100 : 0;

        // Determinar estado (umbrales configurables)
        $status = 'green';
        if ($boxPercentage > 5 || $weightPercentage > 5) {
            $status = 'red';
        } elseif ($boxPercentage > 1 || $weightPercentage > 1) {
            $status = 'yellow';
        }

        return [
            'status' => $status,
            'declared' => [
                'boxes' => $declaredBoxes,
                'weight_kg' => $declaredWeight,
            ],
            'stock' => [
                'boxes' => $stockBoxes,
                'weight_kg' => $stockWeight,
            ],
            'differences' => [
                'boxes' => $boxDifference,
                'weight_kg' => $weightDifference,
                'box_percentage' => round($boxPercentage, 2),
                'weight_percentage' => round($weightPercentage, 2),
            ],
        ];
    }

    // ============================================
    // NODOS DE VENTA Y STOCK EN ÁRBOL DE PROCESOS
    // ============================================

    /**
     * Añadir nodos de venta y stock como hijos de nodos finales o como nodos independientes
     * 
     * @param array $processNodes Array de nodos del árbol de procesos
     * @return array Array de nodos con nodos de venta/stock añadidos
     */
    /**
     * Añadir nodos de venta y stock al árbol de procesos
     * ✨ NUEVO: Agrupa por nodo final (no por producto)
     * - UN SOLO nodo de venta por nodo final con TODOS sus productos
     * - UN SOLO nodo de stock por nodo final con TODOS sus productos
     */
    public function attachSalesAndStockNodes(array $processNodes)
    {
        $lot = $this->lot;
        
        // 1. Obtener datos de venta y stock agrupados por producto (para luego reagrupar por nodo final)
        $salesDataByProduct = $this->getSalesDataByProduct($lot);
        $stockDataByProduct = $this->getStockDataByProduct($lot);
        
        // 2. Añadir nodos de venta y stock como hijos de nodos finales
        //    UN SOLO nodo de venta y UN SOLO nodo de stock por cada nodo final
        $this->attachSalesAndStockNodesToFinalNodes($processNodes, $salesDataByProduct, $stockDataByProduct);
        
        // 3. Añadir nodos huérfanos (productos sin nodo final o con ambigüedad)
        $orphanNodes = $this->createOrphanNodes($salesDataByProduct, $stockDataByProduct, $processNodes);
        
        // Añadir nodos huérfanos al final de processNodes
        return array_merge($processNodes, $orphanNodes);
    }

    /**
     * Recursivamente añadir nodos de venta y stock a nodos finales
     * ✨ Crea UN SOLO nodo de venta y UN SOLO nodo de stock por nodo final
     * 
     * @param array &$nodes Array de nodos del árbol (por referencia)
     * @param array $salesDataByProduct Datos de venta agrupados por producto
     * @param array $stockDataByProduct Datos de stock agrupados por producto
     */
    private function attachSalesAndStockNodesToFinalNodes(array &$nodes, array $salesDataByProduct, array $stockDataByProduct)
    {
        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $this->attachSalesAndStockNodesToFinalNodes($node['children'], $salesDataByProduct, $stockDataByProduct);
            }
            
            if (isset($node['isFinal']) && $node['isFinal'] === true) {
                $finalNodeId = $node['id'];
                
                // Obtener todos los productos que produce este nodo final
                $productIds = [];
                foreach ($node['outputs'] ?? [] as $output) {
                    $productId = $output['productId'] ?? null;
                    if ($productId) {
                        $productIds[] = $productId;
                    }
                }
                
                if (!empty($productIds)) {
                    // Recopilar datos de venta para TODOS los productos de este nodo final
                    $finalNodeSalesData = [];
                    foreach ($productIds as $productId) {
                        if (isset($salesDataByProduct[$productId])) {
                            $finalNodeSalesData[$productId] = $salesDataByProduct[$productId];
                        }
                    }
                    
                    // Recopilar datos de stock para TODOS los productos de este nodo final
                    $finalNodeStockData = [];
                    foreach ($productIds as $productId) {
                        if (isset($stockDataByProduct[$productId])) {
                            $finalNodeStockData[$productId] = $stockDataByProduct[$productId];
                        }
                    }
                    
                    // Crear UN SOLO nodo de venta con todos los productos
                    if (!empty($finalNodeSalesData)) {
                        $salesNode = $this->createSalesNodeForFinalNode($finalNodeId, $finalNodeSalesData);
                        if ($salesNode) {
                            if (!isset($node['children'])) {
                                $node['children'] = [];
                            }
                            $node['children'][] = $salesNode;
                        }
                    }
                    
                    // Crear UN SOLO nodo de stock con todos los productos
                    if (!empty($finalNodeStockData)) {
                        $stockNode = $this->createStockNodeForFinalNode($finalNodeId, $finalNodeStockData);
                        if ($stockNode) {
                            if (!isset($node['children'])) {
                                $node['children'] = [];
                            }
                            $node['children'][] = $stockNode;
                        }
                    }
                }
            }
        }
    }

    /**
     * Obtener datos de venta agrupados por producto
     * 
     * @param string $lot Lote de la producción
     * @return array Datos agrupados por producto y pedido
     */
    private function getSalesDataByProduct(string $lot)
    {
        // Obtener palets asignados a pedidos con cajas del lote disponibles
        $salesPallets = Pallet::query()
            ->whereNotNull('order_id')
            ->whereHas('boxes.box', function ($query) use ($lot) {
                $query->where('lot', $lot)
                      ->whereDoesntHave('productionInputs');
            })
            ->with([
                'order.customer',
                'boxes.box' => function ($query) use ($lot) {
                    $query->where('lot', $lot)
                          ->whereDoesntHave('productionInputs')
                          ->with('product');
                }
            ])
            ->get();
        
        // Agrupar por producto y pedido
        $grouped = [];
        
        foreach ($salesPallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (!$box || !$box->isAvailable || !$box->product || !$pallet->order) {
                    continue;
                }
                
                $productId = $box->product->id;
                $orderId = $pallet->order->id;
                
                $key = "{$productId}-{$orderId}";
                
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'product' => $box->product,
                        'order' => $pallet->order,
                        'pallets' => [],
                    ];
                }
                
                $palletKey = $pallet->id;
                if (!isset($grouped[$key]['pallets'][$palletKey])) {
                    $grouped[$key]['pallets'][$palletKey] = [
                        'pallet' => $pallet,
                        'boxes' => [],
                    ];
                }
                
                $grouped[$key]['pallets'][$palletKey]['boxes'][] = $box;
            }
        }
        
        // Reorganizar por producto
        $byProduct = [];
        foreach ($grouped as $key => $data) {
            $productId = $data['product']->id;
            if (!isset($byProduct[$productId])) {
                $byProduct[$productId] = [];
            }
            $byProduct[$productId][$data['order']->id] = $data;
        }
        
        return $byProduct;
    }

    /**
     * Obtener datos de stock agrupados por producto
     * 
     * @param string $lot Lote de la producción
     * @return array Datos agrupados por producto y almacén
     */
    private function getStockDataByProduct(string $lot)
    {
        // Obtener palets almacenados con cajas del lote disponibles
        $stockPallets = Pallet::query()
            ->stored()  // state_id = 2
            ->whereHas('storedPallet')
            ->whereNull('order_id')  // Solo sin pedido
            ->whereHas('boxes.box', function ($query) use ($lot) {
                $query->where('lot', $lot)
                      ->whereDoesntHave('productionInputs');
            })
            ->with([
                'storedPallet.store',
                'boxes.box' => function ($query) use ($lot) {
                    $query->where('lot', $lot)
                          ->whereDoesntHave('productionInputs')
                          ->with('product');
                }
            ])
            ->get();
        
        // Agrupar por producto y almacén
        $grouped = [];
        
        foreach ($stockPallets as $pallet) {
            if (!$pallet->storedPallet) {
                continue;
            }
            
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box;
                if (!$box || !$box->isAvailable || !$box->product) {
                    continue;
                }
                
                $productId = $box->product->id;
                $storeId = $pallet->storedPallet->store_id;
                
                $key = "{$productId}-{$storeId}";
                
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'product' => $box->product,
                        'store' => $pallet->storedPallet->store,
                        'pallets' => [],
                    ];
                }
                
                $palletKey = $pallet->id;
                if (!isset($grouped[$key]['pallets'][$palletKey])) {
                    $grouped[$key]['pallets'][$palletKey] = [
                        'pallet' => $pallet,
                        'storedPallet' => $pallet->storedPallet,
                        'boxes' => [],
                    ];
                }
                
                $grouped[$key]['pallets'][$palletKey]['boxes'][] = $box;
            }
        }
        
        // Reorganizar por producto
        $byProduct = [];
        foreach ($grouped as $key => $data) {
            $productId = $data['product']->id;
            if (!isset($byProduct[$productId])) {
                $byProduct[$productId] = [];
            }
            $byProduct[$productId][$data['store']->id] = $data;
        }
        
        return $byProduct;
    }


    /**
     * Crear UN SOLO nodo de venta para un nodo final con TODOS sus productos
     * ✨ Agrupa todos los productos del nodo final en un solo nodo
     * 
     * @param int $finalNodeId ID del nodo final
     * @param array $salesDataByProduct Datos de venta agrupados por producto {productId => {orderId => data}}
     * @return array|null Nodo de venta o null si no hay datos
     */
    private function createSalesNodeForFinalNode(int $finalNodeId, array $salesDataByProduct)
    {
        if (empty($salesDataByProduct)) {
            return null;
        }
        
        // Paso 1: Agrupar por pedido (no por producto)
        // Recopilar todos los pedidos y sus productos
        $ordersMap = [];  // orderId => {order, products: {productId => {...}}}
        $allProducts = [];  // Para obtener lista de productos únicos
        $totalBoxes = 0;
        $totalNetWeight = 0;
        $totalPallets = 0;
        
        foreach ($salesDataByProduct as $productId => $productOrders) {
            foreach ($productOrders as $orderId => $orderData) {
                $order = $orderData['order'];
                
                if (!isset($ordersMap[$orderId])) {
                    $ordersMap[$orderId] = [
                        'order' => $order,
                        'products' => [],
                    ];
                }
                
                // Calcular palets y totales para este producto en este pedido
                $palletsData = [];
                $productBoxes = 0;
                $productNetWeight = 0;
                
                foreach ($orderData['pallets'] as $palletData) {
                    $pallet = $palletData['pallet'];
                    $boxes = collect($palletData['boxes']);
                    
                    $availableBoxesCount = $boxes->count();
                    $totalAvailableWeight = $boxes->sum('net_weight');
                    
                    $palletsData[] = [
                        'id' => $pallet->id,
                        'availableBoxesCount' => $availableBoxesCount,
                        'totalAvailableWeight' => round($totalAvailableWeight, 2),
                    ];
                    
                    $productBoxes += $availableBoxesCount;
                    $productNetWeight += $totalAvailableWeight;
                    $totalPallets++;
                }
                
                // Añadir producto al pedido
                $ordersMap[$orderId]['products'][$productId] = [
                    'product' => [
                        'id' => $orderData['product']->id,
                        'name' => $orderData['product']->name,
                    ],
                    'pallets' => $palletsData,
                    'totalBoxes' => $productBoxes,
                    'totalNetWeight' => round($productNetWeight, 2),
                ];
                
                $allProducts[$productId] = $orderData['product'];
                
                $totalBoxes += $productBoxes;
                $totalNetWeight += $productNetWeight;
            }
        }
        
        // Paso 2: Convertir ordersMap a array de orders con products como array
        $ordersData = [];
        foreach ($ordersMap as $orderId => $orderInfo) {
            $orderProducts = array_values($orderInfo['products']);
            
            // Calcular totales del pedido
            $orderTotalBoxes = array_sum(array_column($orderProducts, 'totalBoxes'));
            $orderTotalNetWeight = array_sum(array_column($orderProducts, 'totalNetWeight'));
            
            $ordersData[] = [
                'order' => [
                    'id' => $orderInfo['order']->id,
                    'formattedId' => $orderInfo['order']->formatted_id,
                    'customer' => $orderInfo['order']->customer ? [
                        'id' => $orderInfo['order']->customer->id,
                        'name' => $orderInfo['order']->customer->name,
                    ] : null,
                    'loadDate' => $orderInfo['order']->load_date 
                        ? (\Carbon\Carbon::parse($orderInfo['order']->load_date)->toIso8601String())
                        : null,
                    'status' => $orderInfo['order']->status,
                ],
                'products' => $orderProducts,  // 👈 Array de productos
                'totalBoxes' => $orderTotalBoxes,
                'totalNetWeight' => round($orderTotalNetWeight, 2),
            ];
        }
        
        // Paso 3: Crear el nodo de venta
        return [
            'type' => 'sales',
            'id' => "sales-{$finalNodeId}",  // 👈 ID del nodo final
            'parentRecordId' => $finalNodeId,
            'productionId' => $this->id,
            'orders' => $ordersData,
            'totalBoxes' => $totalBoxes,
            'totalNetWeight' => round($totalNetWeight, 2),
            'summary' => [
                'ordersCount' => count($ordersData),
                'productsCount' => count($allProducts),  // 👈 Número de productos diferentes
                'palletsCount' => $totalPallets,
                'boxesCount' => $totalBoxes,
                'netWeight' => round($totalNetWeight, 2),
            ],
            'children' => [],
        ];
    }

    /**
     * Crear UN SOLO nodo de stock para un nodo final con TODOS sus productos
     * ✨ Agrupa todos los productos del nodo final en un solo nodo
     * 
     * @param int $finalNodeId ID del nodo final
     * @param array $stockDataByProduct Datos de stock agrupados por producto {productId => {storeId => data}}
     * @return array|null Nodo de stock o null si no hay datos
     */
    private function createStockNodeForFinalNode(int $finalNodeId, array $stockDataByProduct)
    {
        if (empty($stockDataByProduct)) {
            return null;
        }
        
        // Paso 1: Agrupar por almacén (no por producto)
        // Recopilar todos los almacenes y sus productos
        $storesMap = [];  // storeId => {store, products: {productId => {...}}}
        $allProducts = [];  // Para obtener lista de productos únicos
        $totalBoxes = 0;
        $totalNetWeight = 0;
        $totalPallets = 0;
        
        foreach ($stockDataByProduct as $productId => $productStores) {
            foreach ($productStores as $storeId => $storeData) {
                $store = $storeData['store'];
                
                if (!isset($storesMap[$storeId])) {
                    $storesMap[$storeId] = [
                        'store' => $store,
                        'products' => [],
                    ];
                }
                
                // Calcular palets y totales para este producto en este almacén
                $palletsData = [];
                $productBoxes = 0;
                $productNetWeight = 0;
                
                foreach ($storeData['pallets'] as $palletData) {
                    $pallet = $palletData['pallet'];
                    $storedPallet = $palletData['storedPallet'];
                    $boxes = collect($palletData['boxes']);
                    
                    $availableBoxesCount = $boxes->count();
                    $totalAvailableWeight = $boxes->sum('net_weight');
                    
                    $palletsData[] = [
                        'id' => $pallet->id,
                        'availableBoxesCount' => $availableBoxesCount,
                        'totalAvailableWeight' => round($totalAvailableWeight, 2),
                        'position' => $storedPallet->position,
                    ];
                    
                    $productBoxes += $availableBoxesCount;
                    $productNetWeight += $totalAvailableWeight;
                    $totalPallets++;
                }
                
                // Añadir producto al almacén
                $storesMap[$storeId]['products'][$productId] = [
                    'product' => [
                        'id' => $storeData['product']->id,
                        'name' => $storeData['product']->name,
                    ],
                    'pallets' => $palletsData,
                    'totalBoxes' => $productBoxes,
                    'totalNetWeight' => round($productNetWeight, 2),
                ];
                
                $allProducts[$productId] = $storeData['product'];
                
                $totalBoxes += $productBoxes;
                $totalNetWeight += $productNetWeight;
            }
        }
        
        // Paso 2: Convertir storesMap a array de stores con products como array
        $storesData = [];
        foreach ($storesMap as $storeId => $storeInfo) {
            $storeProducts = array_values($storeInfo['products']);
            
            // Calcular totales del almacén
            $storeTotalBoxes = array_sum(array_column($storeProducts, 'totalBoxes'));
            $storeTotalNetWeight = array_sum(array_column($storeProducts, 'totalNetWeight'));
            
            $storesData[] = [
                'store' => [
                    'id' => $storeInfo['store']->id,
                    'name' => $storeInfo['store']->name,
                    'temperature' => $storeInfo['store']->temperature,
                ],
                'products' => $storeProducts,  // 👈 Array de productos
                'totalBoxes' => $storeTotalBoxes,
                'totalNetWeight' => round($storeTotalNetWeight, 2),
            ];
        }
        
        // Paso 3: Crear el nodo de stock
        return [
            'type' => 'stock',
            'id' => "stock-{$finalNodeId}",  // 👈 ID del nodo final
            'parentRecordId' => $finalNodeId,
            'productionId' => $this->id,
            'stores' => $storesData,
            'totalBoxes' => $totalBoxes,
            'totalNetWeight' => round($totalNetWeight, 2),
            'summary' => [
                'storesCount' => count($storesData),
                'productsCount' => count($allProducts),  // 👈 Número de productos diferentes
                'palletsCount' => $totalPallets,
                'boxesCount' => $totalBoxes,
                'netWeight' => round($totalNetWeight, 2),
            ],
            'children' => [],
        ];
    }

    /**
     * Identificar nodos finales y sus productos
     * Retorna un mapa: productId => [nodeId1, nodeId2, ...]
     * 
     * @param array $nodes Array de nodos del árbol
     * @return array Mapa de productos a sus nodos finales
     */
    private function collectFinalNodesByProduct(array $nodes)
    {
        $finalNodesByProduct = [];
        
        foreach ($nodes as $node) {
            if (isset($node['isFinal']) && $node['isFinal'] === true) {
                foreach ($node['outputs'] ?? [] as $output) {
                    $productId = $output['productId'] ?? null;
                    if ($productId) {
                        if (!isset($finalNodesByProduct[$productId])) {
                            $finalNodesByProduct[$productId] = [];
                        }
                        $finalNodesByProduct[$productId][] = $node['id'];
                    }
                }
            }
            
            if (!empty($node['children'])) {
                $childMap = $this->collectFinalNodesByProduct($node['children']);
                foreach ($childMap as $productId => $nodeIds) {
                    if (!isset($finalNodesByProduct[$productId])) {
                        $finalNodesByProduct[$productId] = [];
                    }
                    $finalNodesByProduct[$productId] = array_merge($finalNodesByProduct[$productId], $nodeIds);
                }
            }
        }
        
        return $finalNodesByProduct;
    }

    /**
     * Crear nodos huérfanos (productos sin nodo final o con múltiples nodos finales)
     * Para nodos huérfanos, agrupamos por producto (un nodo por producto)
     * 
     * @param array $salesDataByProduct Datos de venta agrupados por producto
     * @param array $stockDataByProduct Datos de stock agrupados por producto
     * @param array $processNodes Nodos del árbol de procesos (para identificar nodos finales)
     * @return array Array de nodos huérfanos
     */
    private function createOrphanNodes(array $salesDataByProduct, array $stockDataByProduct, array $processNodes)
    {
        $orphanNodes = [];
        
        // Identificar qué productos tienen nodos finales únicos
        $finalNodesByProduct = $this->collectFinalNodesByProduct($processNodes);
        
        // Para cada producto, verificar si es huérfano (sin nodo final o con múltiples)
        $orphanProductIds = [];
        
        // Identificar productos huérfanos de venta
        foreach ($salesDataByProduct as $productId => $data) {
            if (!isset($finalNodesByProduct[$productId]) || count($finalNodesByProduct[$productId]) !== 1) {
                $orphanProductIds[$productId] = true;
            }
        }
        
        // Identificar productos huérfanos de stock
        foreach ($stockDataByProduct as $productId => $data) {
            if (!isset($finalNodesByProduct[$productId]) || count($finalNodesByProduct[$productId]) !== 1) {
                $orphanProductIds[$productId] = true;
            }
        }
        
        // Agrupar productos huérfanos por producto
        // Para productos huérfanos, creamos un nodo por producto (similar a v2 pero sin padre)
        foreach ($orphanProductIds as $productId => $_) {
            // Nodo de venta huérfano
            if (isset($salesDataByProduct[$productId])) {
                $orphanSalesData = [$productId => $salesDataByProduct[$productId]];
                // Crear un nodo "ficticio" con ID negativo para huérfanos
                $orphanNode = $this->createSalesNodeForFinalNode(-$productId, $orphanSalesData);
                if ($orphanNode) {
                    $orphanNode['parentRecordId'] = null;  // Sin padre
                    $orphanNode['id'] = "sales-orphan-{$productId}";  // ID diferente
                    $orphanNodes[] = $orphanNode;
                }
            }
            
            // Nodo de stock huérfano
            if (isset($stockDataByProduct[$productId])) {
                $orphanStockData = [$productId => $stockDataByProduct[$productId]];
                // Crear un nodo "ficticio" con ID negativo para huérfanos
                $orphanNode = $this->createStockNodeForFinalNode(-$productId, $orphanStockData);
                if ($orphanNode) {
                    $orphanNode['parentRecordId'] = null;  // Sin padre
                    $orphanNode['id'] = "stock-orphan-{$productId}";  // ID diferente
                    $orphanNodes[] = $orphanNode;
                }
            }
        }
        
        return $orphanNodes;
    }
}
