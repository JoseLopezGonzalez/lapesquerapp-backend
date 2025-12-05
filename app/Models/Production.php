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
     * Obtener solo los inputs de materia prima desde stock
     * ✨ Solo cuenta las entradas de stock (no incluye consumos de outputs del padre)
     * 
     * @return array ['weight' => float, 'boxes' => int]
     */
    private function getStockInputsTotals()
    {
        // Obtener solo inputs de stock (materia prima)
        // allInputs() ya obtiene solo ProductionInput (cajas desde stock)
        // No incluye ProductionOutputConsumption (consumos de outputs del padre)
        $stockInputs = $this->allInputs()
            ->with('box')
            ->get();
        
        $totalWeight = $stockInputs->sum(function ($input) {
            return $input->box->net_weight ?? 0;
        });
        
        $totalBoxes = $stockInputs->count();
        
        return [
            'weight' => $totalWeight,
            'boxes' => $totalBoxes,
        ];
    }

    /**
     * Obtener solo los outputs de nodos finales (totales)
     * ✨ Solo cuenta las salidas de nodos finales (no incluye outputs intermedios)
     * 
     * @return array ['weight' => float, 'boxes' => int]
     */
    private function getFinalNodesOutputsTotals()
    {
        $finalOutputs = $this->getFinalNodesOutputs();
        
        $totalWeight = $finalOutputs->sum('weight_kg');
        $totalBoxes = $finalOutputs->sum('boxes');
        
        return [
            'weight' => $totalWeight,
            'boxes' => $totalBoxes,
        ];
    }

    /**
     * Calcular totales globales del lote completo
     * Incluye merma (waste) y rendimiento (yield) como en ProductionRecord
     * 
     * ⚠️ IMPORTANTE: 
     * - Entradas: Solo materia prima desde stock (no incluye consumos de outputs del padre)
     * - Salidas: Solo outputs de nodos finales (no incluye outputs intermedios)
     * 
     * Lógica:
     * - Si hay pérdida (input > output): waste > 0, yield = 0
     * - Si hay ganancia (input < output): yield > 0, waste = 0
     * - Si es neutro (input = output): ambos en 0
     */
    public function calculateGlobalTotals()
    {
        // ✨ CORRECCIÓN: Solo contar inputs de stock (materia prima)
        $stockInputsTotals = $this->getStockInputsTotals();
        $totalInputWeight = $stockInputsTotals['weight'];
        $totalInputBoxes = $stockInputsTotals['boxes'];
        
        // ✨ CORRECCIÓN: Solo contar outputs de nodos finales
        $finalOutputsTotals = $this->getFinalNodesOutputsTotals();
        $totalOutputWeight = $finalOutputsTotals['weight'];
        $totalOutputBoxes = $finalOutputsTotals['boxes'];
        
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
     * Obtener solo los outputs de nodos finales
     * ✨ Solo cuenta las salidas de nodos finales (no incluye outputs intermedios)
     * 
     * @return \Illuminate\Database\Eloquent\Collection Collection de ProductionOutput
     */
    private function getFinalNodesOutputs()
    {
        // Obtener todos los records de esta producción con relaciones cargadas
        $allRecords = $this->records()
            ->with(['inputs', 'children', 'outputs.product'])
            ->get();
        
        // Filtrar solo los que son nodos finales
        // Usar las relaciones cargadas directamente para evitar queries adicionales
        $finalRecords = $allRecords->filter(function ($record) {
            // Verificar condiciones de nodo final usando relaciones cargadas
            $hasNoInputs = $record->inputs->isEmpty();
            $hasNoChildren = $record->children->isEmpty();
            $hasOutputs = $record->outputs->isNotEmpty();
            
            return $hasNoInputs && $hasNoChildren && $hasOutputs;
        });
        
        // Obtener todos los outputs de los nodos finales
        $finalOutputs = collect();
        foreach ($finalRecords as $record) {
            $finalOutputs = $finalOutputs->merge($record->outputs);
        }
        
        return $finalOutputs;
    }

    /**
     * Obtener conciliación detallada por producto
     * ✨ Muestra para cada producto producido: producido, en venta, en stock, re-procesado, balance
     * ⚠️ IMPORTANTE: Solo cuenta las salidas de nodos finales (no outputs intermedios)
     * 
     * @return array Conciliación detallada con productos y resumen
     */
    public function getDetailedReconciliationByProduct(): array
    {
        $lot = $this->lot;
        
        // 1. Obtener todos los productos producidos (SOLO de nodos finales)
        // ✨ CORRECCIÓN: Solo contar outputs de nodos finales, no todos los outputs
        $finalOutputs = $this->getFinalNodesOutputs();
        
        $producedByProduct = [];
        foreach ($finalOutputs as $output) {
            $productId = $output->product_id;
            if (!$productId) {
                continue;
            }
            
            // Asegurar que el producto esté cargado
            if (!$output->relationLoaded('product')) {
                $output->load('product');
            }
            
            if (!isset($producedByProduct[$productId])) {
                $producedByProduct[$productId] = [
                    'product' => $output->product,
                    'weight' => 0,
                    'boxes' => 0,
                ];
            }
            
            $producedByProduct[$productId]['weight'] += (float) $output->weight_kg;
            $producedByProduct[$productId]['boxes'] += (int) $output->boxes;
        }
        
        // 2. Obtener datos de venta, stock y re-procesados
        $salesData = $this->getSalesDataByProduct($lot);
        $stockData = $this->getStockDataByProduct($lot);
        $reprocessedData = $this->getReprocessedDataByProduct($lot);
        
        // 3. Calcular conciliación por producto
        $productsData = [];
        $summary = [
            'totalProducts' => 0,
            'productsOk' => 0,
            'productsWarning' => 0,
            'productsError' => 0,
            'totalProducedWeight' => 0,
            'totalContabilizedWeight' => 0,
            'totalBalanceWeight' => 0,
            'overallStatus' => 'ok',
        ];
        
        foreach ($producedByProduct as $productId => $producedInfo) {
            $product = $producedInfo['product'];
            if (!$product) {
                continue;
            }
            
            $producedWeight = $producedInfo['weight'];
            $producedBoxes = $producedInfo['boxes'];
            
            // Calcular en venta
            $inSalesWeight = 0;
            $inSalesBoxes = 0;
            if (isset($salesData[$productId])) {
                foreach ($salesData[$productId] as $orderData) {
                    foreach ($orderData['pallets'] as $palletData) {
                        $boxes = collect($palletData['boxes']);
                        $inSalesBoxes += $boxes->count();
                        $inSalesWeight += $boxes->sum('net_weight');
                    }
                }
            }
            
            // Calcular en stock
            $inStockWeight = 0;
            $inStockBoxes = 0;
            if (isset($stockData[$productId])) {
                foreach ($stockData[$productId] as $storeData) {
                    foreach ($storeData['pallets'] as $palletData) {
                        $boxes = collect($palletData['boxes']);
                        $inStockBoxes += $boxes->count();
                        $inStockWeight += $boxes->sum('net_weight');
                    }
                }
            }
            
            // Calcular re-procesado
            $reprocessedWeight = 0;
            $reprocessedBoxes = 0;
            if (isset($reprocessedData[$productId])) {
                foreach ($reprocessedData[$productId] as $processData) {
                    $boxes = collect($processData['boxes']);
                    $reprocessedBoxes += $boxes->count();
                    $reprocessedWeight += $boxes->sum('net_weight');
                }
            }
            
            // Calcular balance
            $contabilizedWeight = $inSalesWeight + $inStockWeight + $reprocessedWeight;
            $balanceWeight = $producedWeight - $contabilizedWeight;
            $balancePercentage = $producedWeight > 0 
                ? ($balanceWeight / $producedWeight) * 100 
                : 0;
            
            // Determinar estado
            $status = 'ok';
            $message = 'Todo contabilizado correctamente';
            
            if (abs($balanceWeight) > 0.01) {
                $absPercentage = abs($balancePercentage);
                if ($absPercentage > 5) {
                    $status = 'error';
                    if ($balanceWeight > 0) {
                        $message = "Faltan " . round($balanceWeight, 2) . "kg (" . round($absPercentage, 2) . "%)";
                    } else {
                        $message = "Hay " . round(abs($balanceWeight), 2) . "kg más contabilizado (" . round($absPercentage, 2) . "%)";
                    }
                } else {
                    $status = 'warning';
                    if ($balanceWeight > 0) {
                        $message = "Faltan " . round($balanceWeight, 2) . "kg (" . round($absPercentage, 2) . "%)";
                    } else {
                        $message = "Hay " . round(abs($balanceWeight), 2) . "kg más contabilizado (" . round($absPercentage, 2) . "%)";
                    }
                }
            }
            
            $productsData[] = [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                ],
                'produced' => [
                    'weight' => round($producedWeight, 2),
                    'boxes' => $producedBoxes,
                ],
                'inSales' => [
                    'weight' => round($inSalesWeight, 2),
                    'boxes' => $inSalesBoxes,
                ],
                'inStock' => [
                    'weight' => round($inStockWeight, 2),
                    'boxes' => $inStockBoxes,
                ],
                'reprocessed' => [
                    'weight' => round($reprocessedWeight, 2),
                    'boxes' => $reprocessedBoxes,
                ],
                'balance' => [
                    'weight' => round($balanceWeight, 2),
                    'percentage' => round($balancePercentage, 2),
                ],
                'status' => $status,
                'message' => $message,
            ];
            
            // Actualizar resumen
            $summary['totalProducts']++;
            if ($status === 'ok') {
                $summary['productsOk']++;
            } elseif ($status === 'warning') {
                $summary['productsWarning']++;
            } else {
                $summary['productsError']++;
            }
            
            $summary['totalProducedWeight'] += $producedWeight;
            $summary['totalContabilizedWeight'] += $contabilizedWeight;
            $summary['totalBalanceWeight'] += $balanceWeight;
        }
        
        // Determinar estado general
        $totalBalanceAbs = abs($summary['totalBalanceWeight']);
        $totalBalancePercentage = $summary['totalProducedWeight'] > 0
            ? ($totalBalanceAbs / $summary['totalProducedWeight']) * 100
            : 0;
        
        if ($totalBalanceAbs > 0.01) {
            if ($totalBalancePercentage > 5) {
                $summary['overallStatus'] = 'error';
            } else {
                $summary['overallStatus'] = 'warning';
            }
        }
        
        $summary['totalProducedWeight'] = round($summary['totalProducedWeight'], 2);
        $summary['totalContabilizedWeight'] = round($summary['totalContabilizedWeight'], 2);
        $summary['totalBalanceWeight'] = round($summary['totalBalanceWeight'], 2);
        
        return [
            'products' => $productsData,
            'summary' => $summary,
        ];
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
     * Añadir nodos de venta, stock, re-procesados y faltantes al árbol de procesos
     * ✨ NUEVO: Agrupa por nodo final (no por producto)
     * - UN SOLO nodo de venta por nodo final con TODOS sus productos
     * - UN SOLO nodo de stock por nodo final con TODOS sus productos
     * - UN SOLO nodo de re-procesados por nodo final con TODOS sus productos
     * - UN SOLO nodo de balance (faltantes y sobras) por nodo final con TODOS sus productos
     */
    public function attachSalesAndStockNodes(array $processNodes)
    {
        $lot = $this->lot;
        
        // 1. Obtener datos agrupados por producto (para luego reagrupar por nodo final)
        $salesDataByProduct = $this->getSalesDataByProduct($lot);
        $stockDataByProduct = $this->getStockDataByProduct($lot);
        $reprocessedDataByProduct = $this->getReprocessedDataByProduct($lot);
        $missingDataByProduct = $this->getMissingDataByProduct($lot);
        
        // 2. Añadir nodos como hijos de nodos finales
        //    UN SOLO nodo de cada tipo por cada nodo final
        $this->attachAllNodesToFinalNodes(
            $processNodes, 
            $salesDataByProduct, 
            $stockDataByProduct,
            $reprocessedDataByProduct,
            $missingDataByProduct
        );
        
        // 3. Añadir nodos huérfanos (productos sin nodo final o con ambigüedad)
        $orphanNodes = $this->createOrphanNodes(
            $salesDataByProduct, 
            $stockDataByProduct, 
            $reprocessedDataByProduct,
            $missingDataByProduct,
            $processNodes
        );
        
        // Añadir nodos huérfanos al final de processNodes
        return array_merge($processNodes, $orphanNodes);
    }

    /**
     * Recursivamente añadir nodos de venta, stock, re-procesados y faltantes a nodos finales
     * ✨ Crea UN SOLO nodo de cada tipo por nodo final
     * 
     * @param array &$nodes Array de nodos del árbol (por referencia)
     * @param array $salesDataByProduct Datos de venta agrupados por producto
     * @param array $stockDataByProduct Datos de stock agrupados por producto
     * @param array $reprocessedDataByProduct Datos de re-procesados agrupados por producto
     * @param array $missingDataByProduct Datos de faltantes físicos agrupados por producto (solo cajas físicas, no el balance completo)
     */
    private function attachAllNodesToFinalNodes(
        array &$nodes, 
        array $salesDataByProduct, 
        array $stockDataByProduct,
        array $reprocessedDataByProduct,
        array $missingDataByProduct
    )
    {
        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $this->attachAllNodesToFinalNodes(
                    $node['children'], 
                    $salesDataByProduct, 
                    $stockDataByProduct,
                    $reprocessedDataByProduct,
                    $missingDataByProduct
                );
            }
            
            if (isset($node['isFinal']) && $node['isFinal'] === true) {
                $finalNodeId = $node['id'];
                
                // Obtener todos los productos que produce este nodo final
                $productIds = [];
                $finalNodeOutputs = $node['outputs'] ?? [];
                foreach ($finalNodeOutputs as $output) {
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
                    
                    // Recopilar datos de re-procesados para TODOS los productos de este nodo final
                    $finalNodeReprocessedData = [];
                    foreach ($productIds as $productId) {
                        if (isset($reprocessedDataByProduct[$productId])) {
                            $finalNodeReprocessedData[$productId] = $reprocessedDataByProduct[$productId];
                        }
                    }
                    
                    // Recopilar datos de balance (faltantes y sobras) para TODOS los productos de este nodo final
                    $finalNodeMissingData = [];
                    foreach ($productIds as $productId) {
                        if (isset($missingDataByProduct[$productId])) {
                            $finalNodeMissingData[$productId] = $missingDataByProduct[$productId];
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
                    
                    // Crear UN SOLO nodo de re-procesados con todos los productos
                    if (!empty($finalNodeReprocessedData)) {
                        $reprocessedNode = $this->createReprocessedNodeForFinalNode($finalNodeId, $finalNodeReprocessedData);
                        if ($reprocessedNode) {
                            if (!isset($node['children'])) {
                                $node['children'] = [];
                            }
                            $node['children'][] = $reprocessedNode;
                        }
                    }
                    
                    // Crear UN SOLO nodo de balance (faltantes y sobras) con todos los productos
                    // ✨ Siempre crear el nodo si hay productos, para mostrar el balance completo
                    $balanceNode = $this->createMissingNodeForFinalNode(
                        $finalNodeId,
                        $finalNodeMissingData,
                        $finalNodeSalesData,
                        $finalNodeStockData,
                        $finalNodeReprocessedData,
                        $finalNodeOutputs
                    );
                    if ($balanceNode) {
                        if (!isset($node['children'])) {
                            $node['children'] = [];
                        }
                        $node['children'][] = $balanceNode;
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
     * Obtener datos de re-procesados agrupados por producto
     * Cajas del lote que fueron usadas como materia prima en otro proceso
     * 
     * @param string $lot Lote de la producción
     * @return array Datos agrupados por producto y proceso de destino
     */
    private function getReprocessedDataByProduct(string $lot)
    {
        // Obtener cajas del lote que fueron consumidas en otros procesos
        $reprocessedBoxes = \App\Models\Box::query()
            ->where('lot', $lot)
            ->whereHas('productionInputs')  // Fue usada en otro proceso
            ->with([
                'product',
                'productionInputs.productionRecord.process',
                'productionInputs.productionRecord.production'
            ])
            ->get();
        
        // Agrupar por producto y proceso de destino
        $grouped = [];
        
        foreach ($reprocessedBoxes as $box) {
            if (!$box->product) {
                continue;
            }
            
            $productId = $box->product->id;
            
            // Una caja puede haberse usado en múltiples procesos, pero normalmente es uno
            foreach ($box->productionInputs as $productionInput) {
                $productionRecord = $productionInput->productionRecord;
                if (!$productionRecord) {
                    continue;
                }
                
                $processId = $productionRecord->process_id;
                $productionRecordId = $productionRecord->id;
                
                $key = "{$productId}-{$productionRecordId}";
                
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'product' => $box->product,
                        'process' => $productionRecord->process,
                        'productionRecord' => $productionRecord,
                        'boxes' => [],
                    ];
                }
                
                $grouped[$key]['boxes'][] = $box;
            }
        }
        
        // Reorganizar por producto
        $byProduct = [];
        foreach ($grouped as $key => $data) {
            $productId = $data['product']->id;
            if (!isset($byProduct[$productId])) {
                $byProduct[$productId] = [];
            }
            // Usar productionRecordId como key para agrupar por proceso
            $byProduct[$productId][$data['productionRecord']->id] = $data;
        }
        
        return $byProduct;
    }

    /**
     * Obtener datos de faltantes físicos agrupados por producto
     * Cajas del lote que están disponibles pero no están en venta, stock ni fueron consumidas
     * Nota: Estos son solo las cajas físicas faltantes. El balance completo (incluyendo sobras) 
     * se calcula en createMissingNodeForFinalNode()
     * 
     * @param string $lot Lote de la producción
     * @return array Datos agrupados por producto
     */
    private function getMissingDataByProduct(string $lot)
    {
        // Obtener cajas del lote que:
        // - Están disponibles (isAvailable = true, sin productionInputs)
        // - NO están en venta (sin palet con order_id)
        // - NO están en stock (sin palet almacenado)
        
        // Cajas del lote que están disponibles pero no están contabilizadas
        // Opción 1: No tienen palet (no están en ningún palet)
        $boxesWithoutPallet = \App\Models\Box::query()
            ->where('lot', $lot)
            ->whereDoesntHave('productionInputs')  // No fueron consumidas
            ->whereDoesntHave('palletBox')  // No están en ningún palet
            ->with('product')
            ->get();
        
        // Opción 2: Están en un palet pero el palet NO tiene pedido Y NO está almacenado
        $boxesInUnassignedPallet = \App\Models\Box::query()
            ->where('lot', $lot)
            ->whereDoesntHave('productionInputs')  // No fueron consumidas
            ->whereHas('palletBox.pallet', function ($query) {
                // El palet NO tiene pedido
                $query->whereNull('order_id')
                      // Y NO está almacenado
                      ->whereDoesntHave('storedPallet');
            })
            ->with('product')
            ->get();
        
        // Combinar ambas
        $missingBoxes = $boxesWithoutPallet->merge($boxesInUnassignedPallet)->unique('id');
        
        // Agrupar por producto
        $byProduct = [];
        
        foreach ($missingBoxes as $box) {
            if (!$box->product) {
                continue;
            }
            
            $productId = $box->product->id;
            
            if (!isset($byProduct[$productId])) {
                $byProduct[$productId] = [
                    'product' => $box->product,
                    'boxes' => [],
                ];
            }
            
            $byProduct[$productId]['boxes'][] = $box;
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
     * Crear UN SOLO nodo de re-procesados para un nodo final con TODOS sus productos
     * ✨ Agrupa todos los productos del nodo final que fueron re-procesados
     * 
     * @param int $finalNodeId ID del nodo final
     * @param array $reprocessedDataByProduct Datos de re-procesados agrupados por producto {productId => {productionRecordId => data}}
     * @return array|null Nodo de re-procesados o null si no hay datos
     */
    private function createReprocessedNodeForFinalNode(int $finalNodeId, array $reprocessedDataByProduct)
    {
        if (empty($reprocessedDataByProduct)) {
            return null;
        }
        
        // Paso 1: Agrupar por proceso destino (no por producto)
        // Recopilar todos los procesos y sus productos
        $processesMap = [];  // productionRecordId => {process, productionRecord, products: {productId => {...}}}
        $allProducts = [];  // Para obtener lista de productos únicos
        $totalBoxes = 0;
        $totalNetWeight = 0;
        
        foreach ($reprocessedDataByProduct as $productId => $productProcesses) {
            foreach ($productProcesses as $productionRecordId => $processData) {
                $process = $processData['process'];
                $productionRecord = $processData['productionRecord'];
                
                // Asegurar que el production esté cargado
                if (!$productionRecord->relationLoaded('production')) {
                    $productionRecord->load('production');
                }
                
                if (!isset($processesMap[$productionRecordId])) {
                    $processesMap[$productionRecordId] = [
                        'process' => $process,
                        'productionRecord' => $productionRecord,
                        'products' => [],
                    ];
                }
                
                // Calcular totales para este producto en este proceso
                $boxes = collect($processData['boxes']);
                $productBoxes = $boxes->count();
                $productNetWeight = $boxes->sum('net_weight');
                
                // Añadir producto al proceso
                $processesMap[$productionRecordId]['products'][$productId] = [
                    'product' => [
                        'id' => $processData['product']->id,
                        'name' => $processData['product']->name,
                    ],
                    'boxes' => $boxes->map(function ($box) {
                        return [
                            'id' => $box->id,
                            'netWeight' => round($box->net_weight, 2),
                            'gs1_128' => $box->gs1_128,
                        ];
                    })->values()->toArray(),
                    'totalBoxes' => $productBoxes,
                    'totalNetWeight' => round($productNetWeight, 2),
                ];
                
                $allProducts[$productId] = $processData['product'];
                
                $totalBoxes += $productBoxes;
                $totalNetWeight += $productNetWeight;
            }
        }
        
        // Paso 2: Convertir processesMap a array de processes con products como array
        $processesData = [];
        foreach ($processesMap as $productionRecordId => $processInfo) {
            $processProducts = array_values($processInfo['products']);
            
            // Calcular totales del proceso
            $processTotalBoxes = array_sum(array_column($processProducts, 'totalBoxes'));
            $processTotalNetWeight = array_sum(array_column($processProducts, 'totalNetWeight'));
            
            $processesData[] = [
                'process' => [
                    'id' => $processInfo['process']->id,
                    'name' => $processInfo['process']->name,
                    'type' => $processInfo['process']->type,
                ],
                'productionRecord' => [
                    'id' => $processInfo['productionRecord']->id,
                    'productionId' => $processInfo['productionRecord']->production_id,
                    'startedAt' => $processInfo['productionRecord']->started_at 
                        ? (\Carbon\Carbon::parse($processInfo['productionRecord']->started_at)->toIso8601String())
                        : null,
                    'finishedAt' => $processInfo['productionRecord']->finished_at 
                        ? (\Carbon\Carbon::parse($processInfo['productionRecord']->finished_at)->toIso8601String())
                        : null,
                ],
                'production' => $processInfo['productionRecord']->production ? [
                    'id' => $processInfo['productionRecord']->production->id,
                    'lot' => $processInfo['productionRecord']->production->lot,
                ] : null,
                'products' => $processProducts,  // 👈 Array de productos en este proceso
                'totalBoxes' => $processTotalBoxes,
                'totalNetWeight' => round($processTotalNetWeight, 2),
            ];
        }
        
        // Paso 3: Crear el nodo de re-procesados
        return [
            'type' => 'reprocessed',
            'id' => "reprocessed-{$finalNodeId}",  // 👈 ID del nodo final
            'parentRecordId' => $finalNodeId,
            'productionId' => $this->id,
            'processes' => $processesData,
            'totalBoxes' => $totalBoxes,
            'totalNetWeight' => round($totalNetWeight, 2),
            'summary' => [
                'processesCount' => count($processesData),
                'productsCount' => count($allProducts),  // 👈 Número de productos diferentes
                'boxesCount' => $totalBoxes,
                'netWeight' => round($totalNetWeight, 2),
            ],
            'children' => [],
        ];
    }

    /**
     * Crear UN SOLO nodo de balance (faltantes y sobras) para un nodo final con TODOS sus productos
     * ✨ Agrupa todos los productos del nodo final que tienen desbalance (faltantes positivos o sobras negativas)
     * 
     * @param int $finalNodeId ID del nodo final
     * @param array $missingDataByProduct Datos de faltantes físicos agrupados por producto (solo cajas físicas, no el balance completo)
     * @param array $salesDataByProduct Datos de venta (para calcular totales)
     * @param array $stockDataByProduct Datos de stock (para calcular totales)
     * @param array $reprocessedDataByProduct Datos de re-procesados (para calcular totales)
     * @param array $finalNodeOutputs Outputs del nodo final (para obtener producción)
     * @return array|null Nodo de balance o null si no hay datos
     */
    private function createMissingNodeForFinalNode(
        int $finalNodeId, 
        array $missingDataByProduct,
        array $salesDataByProduct,
        array $stockDataByProduct,
        array $reprocessedDataByProduct,
        array $finalNodeOutputs
    ) {
        // ✨ Calcular balance completo (faltantes y sobras) para TODOS los productos del nodo final
        // No solo los que tienen cajas físicas faltantes. Incluye también productos con sobras (más contabilizado que producido)
        
        // Paso 1: Obtener todos los productos del nodo final desde los outputs
        $allProductsInFinalNode = [];
        foreach ($finalNodeOutputs as $output) {
            $productId = $output['productId'] ?? null;
            if ($productId && !isset($allProductsInFinalNode[$productId])) {
                // El producto puede venir en el output directamente
                $product = null;
                if (isset($output['product']) && isset($output['product']['id'])) {
                    // Crear un objeto producto simple desde los datos del output
                    $product = (object) [
                        'id' => $output['product']['id'],
                        'name' => $output['product']['name'] ?? 'Producto ' . $productId,
                    ];
                } else {
                    // Si no está en el output, buscarlo en los datos disponibles
                    if (isset($salesDataByProduct[$productId])) {
                        $firstOrder = reset($salesDataByProduct[$productId]);
                        $product = $firstOrder['product'] ?? null;
                    } elseif (isset($stockDataByProduct[$productId])) {
                        $firstStore = reset($stockDataByProduct[$productId]);
                        $product = $firstStore['product'] ?? null;
                    } elseif (isset($reprocessedDataByProduct[$productId])) {
                        $firstProcess = reset($reprocessedDataByProduct[$productId]);
                        $product = $firstProcess['product'] ?? null;
                    } elseif (isset($missingDataByProduct[$productId])) {
                        $product = $missingDataByProduct[$productId]['product'] ?? null;
                    }
                    
                    // Si aún no lo encontramos, buscarlo en la BD
                    if (!$product) {
                        $productModel = \App\Models\Product::find($productId);
                        if ($productModel) {
                            $product = $productModel;
                        }
                    }
                }
                
                if ($product) {
                    $allProductsInFinalNode[$productId] = $product;
                }
            }
        }
        
        if (empty($allProductsInFinalNode)) {
            return null;
        }
        
        // Paso 2: Calcular balance completo por producto
        $productsData = [];
        $totalMissingBoxes = 0;
        $totalMissingWeight = 0;
        
        foreach ($allProductsInFinalNode as $productId => $product) {
            // Obtener producción del nodo final para este producto
            $produced = 0;
            $producedBoxes = 0;
            foreach ($finalNodeOutputs as $output) {
                if (($output['productId'] ?? null) == $productId) {
                    $produced += (float) ($output['weightKg'] ?? 0);
                    $producedBoxes += (int) ($output['boxes'] ?? 0);
                }
            }
            
            // Calcular en venta
            $inSales = 0;
            $inSalesBoxes = 0;
            if (isset($salesDataByProduct[$productId])) {
                foreach ($salesDataByProduct[$productId] as $orderData) {
                    foreach ($orderData['pallets'] as $palletData) {
                        $boxes = collect($palletData['boxes']);
                        $inSalesBoxes += $boxes->count();
                        $inSales += $boxes->sum('net_weight');
                    }
                }
            }
            
            // Calcular en stock
            $inStock = 0;
            $inStockBoxes = 0;
            if (isset($stockDataByProduct[$productId])) {
                foreach ($stockDataByProduct[$productId] as $storeData) {
                    foreach ($storeData['pallets'] as $palletData) {
                        $boxes = collect($palletData['boxes']);
                        $inStockBoxes += $boxes->count();
                        $inStock += $boxes->sum('net_weight');
                    }
                }
            }
            
            // Calcular re-procesados
            $reprocessed = 0;
            $reprocessedBoxes = 0;
            if (isset($reprocessedDataByProduct[$productId])) {
                foreach ($reprocessedDataByProduct[$productId] as $processData) {
                    $boxes = collect($processData['boxes']);
                    $reprocessedBoxes += $boxes->count();
                    $reprocessed += $boxes->sum('net_weight');
                }
            }
            
            // Calcular balance teórico: Producido - Venta - Stock - Re-procesado
            // Positivo = faltante, Negativo = sobrante (más contabilizado que producido)
            $calculatedMissing = $produced - $inSales - $inStock - $reprocessed;
            
            // Obtener cajas físicas faltantes (si existen)
            $missingBoxes = collect([]);
            $missingBoxesCount = 0;
            $missingWeight = 0;
            if (isset($missingDataByProduct[$productId])) {
                $missingBoxes = collect($missingDataByProduct[$productId]['boxes'] ?? []);
                $missingBoxesCount = $missingBoxes->count();
                $missingWeight = $missingBoxes->sum('net_weight');
            }
            
            // Usar el balance calculado o el de cajas físicas
            // Si hay cajas físicas, usar ese peso; si no, usar el calculado (puede ser negativo = sobrante)
            if ($missingBoxesCount > 0) {
                // Hay cajas físicas faltantes
                $finalMissingWeight = $missingWeight;
                $finalMissingBoxes = $missingBoxesCount;
            } else {
                // No hay cajas físicas, usar el cálculo teórico
                // Si es negativo = sobrante (más contabilizado que producido, posible error de datos)
                // Si es positivo = faltante (no contabilizado)
                $finalMissingWeight = $calculatedMissing; // Permitir valores negativos para detectar sobras/errores
                $finalMissingBoxes = 0; // No sabemos cuántas cajas son
            }
            
            // Calcular porcentaje de faltantes (solo si es positivo, no aplica a sobras)
            $missingPercentage = ($produced > 0 && $finalMissingWeight > 0) 
                ? ($finalMissingWeight / $produced) * 100 
                : 0;
            
            // Incluir productos con faltantes (positivos) o sobras (negativos)
            // Mostrar si hay faltantes positivos o si hay más contabilizado que producido (sobrante/error)
            $hasPositiveMissing = $finalMissingWeight > 0.01;
            $hasOverCount = $calculatedMissing < -0.01; // Más en venta/stock que producido = sobrante
            
            if ($hasPositiveMissing || $hasOverCount) {
                $productsData[] = [
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                    ],
                    'produced' => [
                        'boxes' => $producedBoxes,
                        'weight' => round($produced, 2),
                    ],
                    'inSales' => [
                        'boxes' => $inSalesBoxes,
                        'weight' => round($inSales, 2),
                    ],
                    'inStock' => [
                        'boxes' => $inStockBoxes,
                        'weight' => round($inStock, 2),
                    ],
                    'reprocessed' => [
                        'boxes' => $reprocessedBoxes,
                        'weight' => round($reprocessed, 2),
                    ],
                    'balance' => [
                        'boxes' => $finalMissingBoxes,
                        'weight' => round($finalMissingWeight, 2),
                        'percentage' => round($missingPercentage, 2),
                    ],
                    'boxes' => $missingBoxes->map(function ($box) {
                        return [
                            'id' => $box->id,
                            'netWeight' => round($box->net_weight, 2),
                            'gs1_128' => $box->gs1_128 ?? null,
                            'location' => null,  // Sin ubicación asignada
                        ];
                    })->values()->toArray(),
                ];
                
                $totalMissingBoxes += $finalMissingBoxes;
                // Sumar el balance (puede ser negativo = sobrante, positivo = faltante)
                $totalMissingWeight += $finalMissingWeight;
            }
        }
        
        // Solo crear el nodo si hay productos con faltantes (positivos) o sobras (negativos)
        if (empty($productsData)) {
            return null;
        }
        
        // Paso 3: Crear el nodo de balance (faltantes y sobras)
        return [
            'type' => 'balance',
            'id' => "balance-{$finalNodeId}",  // 👈 ID del nodo final
            'parentRecordId' => $finalNodeId,
            'productionId' => $this->id,
            'products' => $productsData,
            'summary' => [
                'productsCount' => count($productsData),
                'totalBalanceBoxes' => $totalMissingBoxes,
                'totalBalanceWeight' => round($totalMissingWeight, 2),
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
     * @param array $reprocessedDataByProduct Datos de re-procesados agrupados por producto
     * @param array $missingDataByProduct Datos de faltantes físicos agrupados por producto (solo cajas físicas, no el balance completo)
     * @param array $processNodes Nodos del árbol de procesos (para identificar nodos finales)
     * @return array Array de nodos huérfanos
     */
    private function createOrphanNodes(
        array $salesDataByProduct, 
        array $stockDataByProduct,
        array $reprocessedDataByProduct,
        array $missingDataByProduct,
        array $processNodes
    )
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
        
        // Identificar productos huérfanos de re-procesados
        foreach ($reprocessedDataByProduct as $productId => $data) {
            if (!isset($finalNodesByProduct[$productId]) || count($finalNodesByProduct[$productId]) !== 1) {
                $orphanProductIds[$productId] = true;
            }
        }
        
        // Identificar productos huérfanos de faltantes
        foreach ($missingDataByProduct as $productId => $data) {
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
            
            // Nodo de re-procesados huérfano
            if (isset($reprocessedDataByProduct[$productId])) {
                $orphanReprocessedData = [$productId => $reprocessedDataByProduct[$productId]];
                // Crear un nodo "ficticio" con ID negativo para huérfanos
                $orphanNode = $this->createReprocessedNodeForFinalNode(-$productId, $orphanReprocessedData);
                if ($orphanNode) {
                    $orphanNode['parentRecordId'] = null;  // Sin padre
                    $orphanNode['id'] = "reprocessed-orphan-{$productId}";  // ID diferente
                    $orphanNodes[] = $orphanNode;
                }
            }
            
            // Nodo de balance huérfano
            if (isset($missingDataByProduct[$productId])) {
                $orphanMissingData = [$productId => $missingDataByProduct[$productId]];
                // Crear un nodo "ficticio" con ID negativo para huérfanos
                // Para faltantes huérfanos, necesitamos pasar datos vacíos para los cálculos
                $orphanNode = $this->createMissingNodeForFinalNode(
                    -$productId,
                    $orphanMissingData,
                    isset($salesDataByProduct[$productId]) ? [$productId => $salesDataByProduct[$productId]] : [],
                    isset($stockDataByProduct[$productId]) ? [$productId => $stockDataByProduct[$productId]] : [],
                    isset($reprocessedDataByProduct[$productId]) ? [$productId => $reprocessedDataByProduct[$productId]] : [],
                    []  // Sin outputs del nodo final (es huérfano)
                );
                if ($orphanNode) {
                    $orphanNode['parentRecordId'] = null;  // Sin padre
                    $orphanNode['id'] = "balance-orphan-{$productId}";  // ID diferente
                    $orphanNodes[] = $orphanNode;
                }
            }
        }
        
        return $orphanNodes;
    }
}
