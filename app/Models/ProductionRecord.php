<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionRecord extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'production_id',
        'parent_record_id',
        'process_id',
        'started_at',
        'finished_at',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Relación con Production (lote)
     */
    public function production()
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    /**
     * Relación con el proceso padre (para construir el árbol)
     */
    public function parent()
    {
        return $this->belongsTo(ProductionRecord::class, 'parent_record_id');
    }

    /**
     * Relación con los procesos hijos
     */
    public function children()
    {
        return $this->hasMany(ProductionRecord::class, 'parent_record_id');
    }

    /**
     * Relación con Process (tipo de proceso)
     */
    public function process()
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * Relación con las entradas (cajas consumidas)
     */
    public function inputs()
    {
        return $this->hasMany(ProductionInput::class, 'production_record_id');
    }

    /**
     * Relación con las salidas (productos producidos)
     */
    public function outputs()
    {
        return $this->hasMany(ProductionOutput::class, 'production_record_id');
    }

    /**
     * Relación con los consumos de outputs del padre (cuando este proceso consume outputs del padre)
     */
    public function parentOutputConsumptions()
    {
        return $this->hasMany(ProductionOutputConsumption::class, 'production_record_id');
    }

    /**
     * Verificar si es un proceso raíz (no tiene padre)
     */
    public function isRoot()
    {
        return $this->parent_record_id === null;
    }

    /**
     * Verificar si es un proceso final (solo tiene outputs, no inputs)
     */
    public function isFinal()
    {
        return $this->inputs()->count() === 0 && $this->outputs()->count() > 0;
    }

    /**
     * Obtener el peso total de las entradas (suma de net_weight de las cajas + consumos de outputs del padre)
     */
    public function getTotalInputWeightAttribute()
    {
        // Peso desde inputs de stock (cajas)
        $stockWeight = $this->inputs()
            ->with('box')
            ->get()
            ->sum(function ($input) {
                return $input->box->net_weight ?? 0;
            });

        // Peso desde consumos de outputs del padre
        $parentOutputWeight = $this->parentOutputConsumptions()->sum('consumed_weight_kg');

        return $stockWeight + $parentOutputWeight;
    }

    /**
     * Obtener el peso total de las salidas
     */
    public function getTotalOutputWeightAttribute()
    {
        return $this->outputs()->sum('weight_kg');
    }

    /**
     * Obtener el número total de cajas de entrada (cajas del stock + cajas consumidas del padre)
     */
    public function getTotalInputBoxesAttribute()
    {
        // Cajas desde inputs de stock
        $stockBoxes = $this->inputs()->count();

        // Cajas desde consumos de outputs del padre
        $parentOutputBoxes = $this->parentOutputConsumptions()->sum('consumed_boxes');

        return $stockBoxes + $parentOutputBoxes;
    }

    /**
     * Obtener el número total de cajas de salida
     */
    public function getTotalOutputBoxesAttribute()
    {
        return $this->outputs()->sum('boxes');
    }

    /**
     * Verificar si el proceso está completado
     */
    public function isCompleted()
    {
        return $this->finished_at !== null;
    }

    /**
     * Construir el árbol de procesos hijos recursivamente
     */
    public function buildTree()
    {
        $this->load('children.process', 'inputs.box.product', 'outputs.product', 'parentOutputConsumptions.productionOutput.product');
        
        foreach ($this->children as $child) {
            $child->buildTree();
        }
        
        return $this;
    }

    /**
     * Calcular los totales del nodo (merma, porcentajes, rendimiento, etc.)
     * 
     * Lógica:
     * - Si hay pérdida (input > output): waste > 0, yield = 0
     * - Si hay ganancia (input < output): yield > 0, waste = 0
     * - Si es neutro (input = output): ambos en 0
     */
    public function calculateNodeTotals()
    {
        $inputWeight = $this->total_input_weight;
        $outputWeight = $this->total_output_weight;
        
        // Calcular diferencia
        $difference = $inputWeight - $outputWeight;
        
        // Si hay pérdida (input > output)
        if ($difference > 0) {
            $waste = $difference;
            $wastePercentage = $inputWeight > 0 ? ($waste / $inputWeight) * 100 : 0;
            $yield = 0;
            $yieldPercentage = 0;
        }
        // Si hay ganancia (input < output)
        elseif ($difference < 0) {
            $waste = 0;
            $wastePercentage = 0;
            $yield = abs($difference); // output - input
            $yieldPercentage = $inputWeight > 0 ? ($yield / $inputWeight) * 100 : 0;
        }
        // Si es neutro (input = output)
        else {
            $waste = 0;
            $wastePercentage = 0;
            $yield = 0;
            $yieldPercentage = 0;
        }

        return [
            'inputWeight' => $inputWeight,
            'outputWeight' => $outputWeight,
            'waste' => $waste,
            'wastePercentage' => round($wastePercentage, 2),
            'yield' => round($yield, 2),
            'yieldPercentage' => round($yieldPercentage, 2),
            'inputBoxes' => $this->total_input_boxes,
            'outputBoxes' => $this->total_output_boxes,
        ];
    }

    /**
     * Obtener la estructura del nodo para el diagrama
     * Compatible con la estructura antigua de diagram_data
     */
    public function getNodeData()
    {
        // Asegurar que las relaciones estén cargadas
        $this->loadMissing(['process', 'inputs.box.product', 'outputs.product', 'children', 'parentOutputConsumptions.productionOutput.product']);

        // Construir árbol de hijos recursivamente
        $childrenData = [];
        foreach ($this->children as $child) {
            $childrenData[] = $child->getNodeData();
        }

        // Preparar inputs desde stock (cajas)
        $inputsData = $this->inputs->map(function ($input) {
            return [
                'id' => $input->id,
                'type' => 'stock_box',
                'box_id' => $input->box_id,
                'box' => [
                    'id' => $input->box->id,
                    'lot' => $input->box->lot,
                    'net_weight' => $input->box->net_weight,
                    'gross_weight' => $input->box->gross_weight,
                ],
                'product' => $input->box->product ? [
                    'id' => $input->box->product->id,
                    'name' => $input->box->product->name,
                ] : null,
                'weight' => $input->box->net_weight ?? 0,
            ];
        })->toArray();

        // Preparar consumos de outputs del padre
        $parentOutputConsumptionsData = $this->parentOutputConsumptions->map(function ($consumption) {
            return [
                'id' => $consumption->id,
                'type' => 'parent_output',
                'production_output_id' => $consumption->production_output_id,
                'production_output' => [
                    'id' => $consumption->productionOutput->id,
                    'product' => $consumption->productionOutput->product ? [
                        'id' => $consumption->productionOutput->product->id,
                        'name' => $consumption->productionOutput->product->name,
                    ] : null,
                    'weight_kg' => $consumption->productionOutput->weight_kg,
                    'boxes' => $consumption->productionOutput->boxes,
                ],
                'consumed_weight_kg' => $consumption->consumed_weight_kg,
                'consumed_boxes' => $consumption->consumed_boxes,
                'weight' => $consumption->consumed_weight_kg,
                'notes' => $consumption->notes,
            ];
        })->toArray();

        // Combinar ambos tipos de inputs
        $allInputsData = array_merge($inputsData, $parentOutputConsumptionsData);

        // Preparar outputs
        $outputsData = $this->outputs->map(function ($output) {
            return [
                'id' => $output->id,
                'product_id' => $output->product_id,
                'product' => $output->product ? [
                    'id' => $output->product->id,
                    'name' => $output->product->name,
                ] : null,
                'lot_id' => $output->lot_id,
                'boxes' => $output->boxes,
                'weight_kg' => $output->weight_kg,
                'average_weight_per_box' => $output->average_weight_per_box,
            ];
        })->toArray();

        // Calcular totales del nodo
        $totals = $this->calculateNodeTotals();

        return [
            'id' => $this->id,
            'production_id' => $this->production_id,
            'parent_record_id' => $this->parent_record_id,
            'process' => $this->process ? [
                'id' => $this->process->id,
                'name' => $this->process->name,
                'type' => $this->process->type,
            ] : null,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'notes' => $this->notes,
            'isRoot' => $this->isRoot(),
            'isFinal' => $this->isFinal(),
            'isCompleted' => $this->isCompleted(),
            'inputs' => $allInputsData,
            'parentOutputConsumptions' => $parentOutputConsumptionsData,
            'outputs' => $outputsData,
            'children' => $childrenData,
            'totals' => $totals,
        ];
    }

    /**
     * Obtener inputs que vienen del stock (cajas)
     */
    public function getStockInputs()
    {
        return $this->inputs;
    }

    /**
     * Obtener consumos de outputs del padre
     */
    public function getParentOutputInputs()
    {
        return $this->parentOutputConsumptions;
    }

    /**
     * Obtener outputs del padre disponibles para consumo
     */
    public function getAvailableParentOutputs()
    {
        if (!$this->parent_record_id) {
            return collect([]); // No tiene padre
        }

        $parent = $this->parent;
        if (!$parent) {
            return collect([]);
        }

        // Obtener outputs del padre
        $parentOutputs = $parent->outputs;

        // Para cada output, calcular cuánto está disponible
        return $parentOutputs->map(function ($output) {
            $consumed = $output->consumptions()->sum('consumed_weight_kg');

            $consumedBoxes = $output->consumptions()->sum('consumed_boxes');

            $available = $output->weight_kg - $consumed;
            $availableBoxes = $output->boxes - $consumedBoxes;

            return [
                'output' => $output,
                'total_weight' => $output->weight_kg,
                'total_boxes' => $output->boxes,
                'consumed_weight' => $consumed,
                'consumed_boxes' => $consumedBoxes,
                'available_weight' => max(0, $available),
                'available_boxes' => max(0, $availableBoxes),
            ];
        })->filter(function ($item) {
            return $item['available_weight'] > 0 || $item['available_boxes'] > 0;
        });
    }
}
