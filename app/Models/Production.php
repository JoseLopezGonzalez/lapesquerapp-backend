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
    public function attachSalesAndStockNodes(array $processNodes)
    {
        $lot = $this->lot;
        
        // 1. Identificar todos los nodos finales y sus productos
        $finalNodesByProduct = [];
        $this->collectFinalNodesAndProducts($processNodes, $finalNodesByProduct);
        
        // 2. Obtener datos de venta y stock (solo del lote de la producción)
        $salesData = $this->getSalesDataByProduct($lot);
        $stockData = $this->getStockDataByProduct($lot);
        
        // 3. Añadir nodos de venta como hijos de nodos finales
        // ⚠️ Si hay múltiples nodos finales para un producto, se crean sin padre
        $this->attachSalesNodesToFinalNodes($processNodes, $finalNodesByProduct, $salesData);
        
        // 4. Añadir nodos de stock como hijos de nodos finales
        // ⚠️ Si hay múltiples nodos finales para un producto, se crean sin padre
        $this->attachStockNodesToFinalNodes($processNodes, $finalNodesByProduct, $stockData);
        
        // 5. Añadir nodos de venta/stock sin padre:
        //    - Sin nodo final correspondiente
        //    - Con múltiples nodos finales (ambigüedad)
        $orphanSalesNodes = $this->createOrphanSalesNodes($salesData, $finalNodesByProduct);
        $orphanStockNodes = $this->createOrphanStockNodes($stockData, $finalNodesByProduct);
        
        // Añadir nodos huérfanos al final de processNodes
        return array_merge($processNodes, $orphanSalesNodes, $orphanStockNodes);
    }

    /**
     * Recursivamente identificar nodos finales y sus productos
     * 
     * @param array $nodes Array de nodos del árbol
     * @param array &$finalNodesByProduct Array de referencia para almacenar nodos finales por producto
     */
    private function collectFinalNodesAndProducts(array $nodes, array &$finalNodesByProduct)
    {
        foreach ($nodes as $node) {
            if (isset($node['isFinal']) && $node['isFinal'] === true) {
                // Extraer productos de los outputs
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
            
            // Recursivamente procesar hijos
            if (!empty($node['children'])) {
                $this->collectFinalNodesAndProducts($node['children'], $finalNodesByProduct);
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
     * Añadir nodos de venta como hijos de nodos finales
     * ⚠️ Solo si hay UN SOLO nodo final para el producto (sin ambigüedad)
     * 
     * @param array &$nodes Array de nodos (por referencia)
     * @param array $finalNodesByProduct Nodos finales agrupados por producto
     * @param array $salesData Datos de venta agrupados por producto
     */
    private function attachSalesNodesToFinalNodes(array &$nodes, array $finalNodesByProduct, array $salesData)
    {
        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $this->attachSalesNodesToFinalNodes($node['children'], $finalNodesByProduct, $salesData);
            }
            
            if (isset($node['isFinal']) && $node['isFinal'] === true) {
                // Buscar productos en outputs de este nodo final
                foreach ($node['outputs'] ?? [] as $output) {
                    $productId = $output['productId'] ?? null;
                    if ($productId && isset($salesData[$productId])) {
                        // ⚠️ Solo asignar si hay UN SOLO nodo final para este producto
                        // Si hay múltiples, se crearán como orphan nodes
                        if (isset($finalNodesByProduct[$productId]) && count($finalNodesByProduct[$productId]) === 1) {
                            // Verificar que este es el único nodo final
                            if ($finalNodesByProduct[$productId][0] === $node['id']) {
                                // Crear UN SOLO nodo de venta para este producto (con desglose de pedidos)
                                $salesNodes = $this->createSalesNodesForProduct($productId, $salesData[$productId], $node['id']);
                                
                                // Añadir como hijo (ahora es un solo nodo)
                                if (!isset($node['children'])) {
                                    $node['children'] = [];
                                }
                                $node['children'] = array_merge($node['children'], $salesNodes);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Añadir nodos de stock como hijos de nodos finales
     * ⚠️ Solo si hay UN SOLO nodo final para el producto (sin ambigüedad)
     * 
     * @param array &$nodes Array de nodos (por referencia)
     * @param array $finalNodesByProduct Nodos finales agrupados por producto
     * @param array $stockData Datos de stock agrupados por producto
     */
    private function attachStockNodesToFinalNodes(array &$nodes, array $finalNodesByProduct, array $stockData)
    {
        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $this->attachStockNodesToFinalNodes($node['children'], $finalNodesByProduct, $stockData);
            }
            
            if (isset($node['isFinal']) && $node['isFinal'] === true) {
                // Buscar productos en outputs de este nodo final
                foreach ($node['outputs'] ?? [] as $output) {
                    $productId = $output['productId'] ?? null;
                    if ($productId && isset($stockData[$productId])) {
                        // ⚠️ Solo asignar si hay UN SOLO nodo final para este producto
                        // Si hay múltiples, se crearán como orphan nodes
                        if (isset($finalNodesByProduct[$productId]) && count($finalNodesByProduct[$productId]) === 1) {
                            // Verificar que este es el único nodo final
                            if ($finalNodesByProduct[$productId][0] === $node['id']) {
                                // Crear UN SOLO nodo de stock para este producto (con desglose de almacenes)
                                $stockNodes = $this->createStockNodesForProduct($productId, $stockData[$productId], $node['id']);
                                
                                // Añadir como hijo (ahora es un solo nodo)
                                if (!isset($node['children'])) {
                                    $node['children'] = [];
                                }
                                $node['children'] = array_merge($node['children'], $stockNodes);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Crear UN SOLO nodo de venta para un producto con desglose de pedidos
     * 
     * @param int $productId ID del producto
     * @param array $data Datos de venta agrupados por pedido
     * @param int|null $parentRecordId ID del nodo final padre (null si no tiene padre)
     * @return array Array con un solo nodo de venta (con desglose de pedidos)
     */
    private function createSalesNodesForProduct(int $productId, array $data, ?int $parentRecordId)
    {
        if (empty($data)) {
            return [];
        }
        
        // Obtener el producto del primer pedido (todos deberían tener el mismo producto)
        $firstOrderData = reset($data);
        $product = $firstOrderData['product'];
        
        // Crear desglose de pedidos
        $ordersData = [];
        $totalBoxes = 0;
        $totalNetWeight = 0;
        $totalPallets = 0;
        
        foreach ($data as $orderId => $orderData) {
            $order = $orderData['order'];
            
            // Calcular totales de palets para este pedido
            $palletsData = [];
            $orderBoxes = 0;
            $orderNetWeight = 0;
            
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
                
                $orderBoxes += $availableBoxesCount;
                $orderNetWeight += $totalAvailableWeight;
                $totalPallets++;
            }
            
            $ordersData[] = [
                'order' => [
                    'id' => $order->id,
                    'formattedId' => $order->formatted_id,
                    'customer' => $order->customer ? [
                        'id' => $order->customer->id,
                        'name' => $order->customer->name,
                    ] : null,
                    'loadDate' => $order->load_date 
                        ? (\Carbon\Carbon::parse($order->load_date)->toIso8601String())
                        : null,
                    'status' => $order->status,
                ],
                'pallets' => $palletsData,
                'totalBoxes' => $orderBoxes,
                'totalNetWeight' => round($orderNetWeight, 2),
                'summary' => [
                    'palletsCount' => count($palletsData),
                    'boxesCount' => $orderBoxes,
                    'netWeight' => round($orderNetWeight, 2),
                ],
            ];
            
            $totalBoxes += $orderBoxes;
            $totalNetWeight += $orderNetWeight;
        }
        
        // Retornar UN SOLO nodo con desglose de pedidos
        return [[
            'type' => 'sales',
            'id' => "sales-{$productId}",
            'parentRecordId' => $parentRecordId,
            'productionId' => $this->id,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
            ],
            'orders' => $ordersData,
            'totalBoxes' => $totalBoxes,
            'totalNetWeight' => round($totalNetWeight, 2),
            'summary' => [
                'ordersCount' => count($ordersData),
                'palletsCount' => $totalPallets,
                'boxesCount' => $totalBoxes,
                'netWeight' => round($totalNetWeight, 2),
            ],
            'children' => [],
        ]];
    }

    /**
     * Crear UN SOLO nodo de stock para un producto con desglose de almacenes
     * 
     * @param int $productId ID del producto
     * @param array $data Datos de stock agrupados por almacén
     * @param int|null $parentRecordId ID del nodo final padre (null si no tiene padre)
     * @return array Array con un solo nodo de stock (con desglose de almacenes)
     */
    private function createStockNodesForProduct(int $productId, array $data, ?int $parentRecordId)
    {
        if (empty($data)) {
            return [];
        }
        
        // Obtener el producto del primer almacén (todos deberían tener el mismo producto)
        $firstStoreData = reset($data);
        $product = $firstStoreData['product'];
        
        // Crear desglose de almacenes
        $storesData = [];
        $totalBoxes = 0;
        $totalNetWeight = 0;
        $totalPallets = 0;
        
        foreach ($data as $storeId => $storeData) {
            $store = $storeData['store'];
            
            // Calcular totales de palets para este almacén
            $palletsData = [];
            $storeBoxes = 0;
            $storeNetWeight = 0;
            
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
                
                $storeBoxes += $availableBoxesCount;
                $storeNetWeight += $totalAvailableWeight;
                $totalPallets++;
            }
            
            $storesData[] = [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'temperature' => $store->temperature,
                ],
                'pallets' => $palletsData,
                'totalBoxes' => $storeBoxes,
                'totalNetWeight' => round($storeNetWeight, 2),
                'summary' => [
                    'palletsCount' => count($palletsData),
                    'boxesCount' => $storeBoxes,
                    'netWeight' => round($storeNetWeight, 2),
                ],
            ];
            
            $totalBoxes += $storeBoxes;
            $totalNetWeight += $storeNetWeight;
        }
        
        // Retornar UN SOLO nodo con desglose de almacenes
        return [[
            'type' => 'stock',
            'id' => "stock-{$productId}",
            'parentRecordId' => $parentRecordId,
            'productionId' => $this->id,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
            ],
            'stores' => $storesData,
            'totalBoxes' => $totalBoxes,
            'totalNetWeight' => round($totalNetWeight, 2),
            'summary' => [
                'storesCount' => count($storesData),
                'palletsCount' => $totalPallets,
                'boxesCount' => $totalBoxes,
                'netWeight' => round($totalNetWeight, 2),
            ],
            'children' => [],
        ]];
    }

    /**
     * Crear nodos de venta sin padre (orphan nodes):
     * - Producto no tiene nodo final
     * - Producto tiene múltiples nodos finales (ambigüedad)
     * 
     * @param array $salesData Datos de venta agrupados por producto
     * @param array $finalNodesByProduct Nodos finales agrupados por producto
     * @return array Array de nodos de venta sin padre (un nodo por producto con desglose de pedidos)
     */
    private function createOrphanSalesNodes(array $salesData, array $finalNodesByProduct)
    {
        $orphanNodes = [];
        
        foreach ($salesData as $productId => $data) {
            // Caso 1: No hay nodo final para este producto
            // Caso 2: Hay múltiples nodos finales (ambigüedad)
            if (!isset($finalNodesByProduct[$productId]) || count($finalNodesByProduct[$productId]) > 1) {
                $orphanNodes = array_merge($orphanNodes, $this->createSalesNodesForProduct($productId, $data, null));
            }
        }
        
        return $orphanNodes;
    }

    /**
     * Crear nodos de stock sin padre (orphan nodes):
     * - Producto no tiene nodo final
     * - Producto tiene múltiples nodos finales (ambigüedad)
     * 
     * @param array $stockData Datos de stock agrupados por producto
     * @param array $finalNodesByProduct Nodos finales agrupados por producto
     * @return array Array de nodos de stock sin padre (un nodo por producto con desglose de almacenes)
     */
    private function createOrphanStockNodes(array $stockData, array $finalNodesByProduct)
    {
        $orphanNodes = [];
        
        foreach ($stockData as $productId => $data) {
            // Caso 1: No hay nodo final para este producto
            // Caso 2: Hay múltiples nodos finales (ambigüedad)
            if (!isset($finalNodesByProduct[$productId]) || count($finalNodesByProduct[$productId]) > 1) {
                $orphanNodes = array_merge($orphanNodes, $this->createStockNodesForProduct($productId, $data, null));
            }
        }
        
        return $orphanNodes;
    }
}
