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
     */
    public function calculateGlobalTotals()
    {
        $totalInputWeight = $this->total_input_weight;
        $totalOutputWeight = $this->total_output_weight;
        $totalWaste = $totalInputWeight - $totalOutputWeight;
        $totalWastePercentage = $totalInputWeight > 0 ? ($totalWaste / $totalInputWeight) * 100 : 0;

        $totalInputBoxes = $this->total_input_boxes;
        $totalOutputBoxes = $this->total_output_boxes;

        return [
            'totalInputWeight' => round($totalInputWeight, 2),
            'totalOutputWeight' => round($totalOutputWeight, 2),
            'totalWaste' => round($totalWaste, 2),
            'totalWastePercentage' => round($totalWastePercentage, 2),
            'totalInputBoxes' => $totalInputBoxes,
            'totalOutputBoxes' => $totalOutputBoxes,
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
}
