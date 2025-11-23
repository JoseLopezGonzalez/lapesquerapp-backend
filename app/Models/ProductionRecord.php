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
     * Obtener el peso total de las entradas (suma de net_weight de las cajas)
     */
    public function getTotalInputWeightAttribute()
    {
        return $this->inputs()
            ->with('box')
            ->get()
            ->sum(function ($input) {
                return $input->box->net_weight ?? 0;
            });
    }

    /**
     * Obtener el peso total de las salidas
     */
    public function getTotalOutputWeightAttribute()
    {
        return $this->outputs()->sum('weight_kg');
    }

    /**
     * Obtener el número total de cajas de entrada
     */
    public function getTotalInputBoxesAttribute()
    {
        return $this->inputs()->count();
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
        $this->load('children.process', 'inputs.box.product', 'outputs.product');
        
        foreach ($this->children as $child) {
            $child->buildTree();
        }
        
        return $this;
    }

    /**
     * Calcular los totales del nodo (merma, porcentajes, etc.)
     */
    public function calculateNodeTotals()
    {
        $inputWeight = $this->total_input_weight;
        $outputWeight = $this->total_output_weight;
        $waste = $inputWeight - $outputWeight;
        $wastePercentage = $inputWeight > 0 ? ($waste / $inputWeight) * 100 : 0;

        return [
            'inputWeight' => $inputWeight,
            'outputWeight' => $outputWeight,
            'waste' => $waste,
            'wastePercentage' => round($wastePercentage, 2),
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
        $this->loadMissing(['process', 'inputs.box.product', 'outputs.product', 'children']);

        // Construir árbol de hijos recursivamente
        $childrenData = [];
        foreach ($this->children as $child) {
            $childrenData[] = $child->getNodeData();
        }

        // Preparar inputs
        $inputsData = $this->inputs->map(function ($input) {
            return [
                'id' => $input->id,
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
            'inputs' => $inputsData,
            'outputs' => $outputsData,
            'children' => $childrenData,
            'totals' => $totals,
        ];
    }
}
