<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOutput extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'production_record_id',
        'product_id',
        'lot_id',
        'boxes',
        'weight_kg',
    ];

    protected $casts = [
        'boxes' => 'integer',
        'weight_kg' => 'decimal:2',
    ];

    /**
     * Array estático para rastrear outputs visitados durante el cálculo de costes
     * Previene recursión infinita en caso de ciclos
     * Se usa como pila (stack) para rastrear la profundidad de la recursión
     */
    protected static $costCalculationStack = [];

    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Validar antes de guardar
        static::saving(function ($output) {
            $output->validateProductionOutputRules();
        });

        // Validar antes de crear
        static::creating(function ($output) {
            $output->validateCreationRules();
        });
    }

    /**
     * Validar reglas de ProductionOutput al guardar
     */
    protected function validateProductionOutputRules(): void
    {
        // Validar que weight_kg > 0
        if ($this->weight_kg <= 0) {
            throw new \InvalidArgumentException(
                'El peso (weight_kg) debe ser mayor que 0.'
            );
        }

        // boxes puede ser 0 según las soluciones del usuario, pero si tiene valor debe ser >= 0
        if ($this->boxes < 0) {
            throw new \InvalidArgumentException(
                'El número de cajas (boxes) no puede ser negativo.'
            );
        }
    }

    /**
     * Validar reglas al crear ProductionOutput
     */
    protected function validateCreationRules(): void
    {
        // Validar que el proceso pertenezca a un lote abierto
        $productionRecord = ProductionRecord::with('production')->find($this->production_record_id);
        if (!$productionRecord) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "El proceso de producción con ID {$this->production_record_id} no existe."
            );
        }

        $production = $productionRecord->production;
        if ($production && $production->closed_at !== null) {
            throw new \InvalidArgumentException(
                'No se pueden agregar salidas a procesos de lotes cerrados. El lote debe estar abierto (closed_at = null).'
            );
        }
    }

    /**
     * Relación con ProductionRecord (proceso)
     */
    public function productionRecord()
    {
        return $this->belongsTo(ProductionRecord::class, 'production_record_id');
    }

    /**
     * Relación con Product (producto producido)
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Relación con los consumos de este output (por procesos hijos)
     */
    public function consumptions()
    {
        return $this->hasMany(ProductionOutputConsumption::class, 'production_output_id');
    }

    /**
     * Relación con las fuentes de este output (trazabilidad de costes)
     */
    public function sources()
    {
        return $this->hasMany(ProductionOutputSource::class, 'production_output_id');
    }

    /**
     * Calcular el peso promedio por caja
     */
    public function getAverageWeightPerBoxAttribute()
    {
        if ($this->boxes > 0) {
            return $this->weight_kg / $this->boxes;
        }
        return 0;
    }

    /**
     * Obtener el peso disponible (no consumido)
     */
    public function getAvailableWeightKgAttribute()
    {
        $consumed = $this->consumptions()->sum('consumed_weight_kg');
        return max(0, $this->weight_kg - $consumed);
    }

    /**
     * Obtener las cajas disponibles (no consumidas)
     */
    public function getAvailableBoxesAttribute()
    {
        $consumed = $this->consumptions()->sum('consumed_boxes');
        return max(0, $this->boxes - $consumed);
    }

    /**
     * Verificar si está completamente consumido
     */
    public function isFullyConsumed()
    {
        return $this->available_weight_kg <= 0 && $this->available_boxes <= 0;
    }

    /**
     * Verificar si está parcialmente consumido
     */
    public function isPartiallyConsumed()
    {
        $hasConsumption = $this->consumptions()->exists();
        return $hasConsumption && !$this->isFullyConsumed();
    }

    /**
     * Obtener el porcentaje consumido por peso
     */
    public function getConsumedWeightPercentageAttribute()
    {
        if ($this->weight_kg <= 0) {
            return 0;
        }
        $consumed = $this->consumptions()->sum('consumed_weight_kg');
        return ($consumed / $this->weight_kg) * 100;
    }

    /**
     * Calcular el coste por kg de este output
     * 
     * Fórmula:
     * cost_per_kg = (
     *     coste_materias_primas + 
     *     coste_produccion_proceso + 
     *     coste_personal_proceso + 
     *     coste_operativos_proceso + 
     *     coste_envases_proceso + 
     *     coste_produccion_lote + 
     *     coste_personal_lote + 
     *     coste_operativos_lote + 
     *     coste_envases_lote
     * ) / weight_kg
     */
    public function getCostPerKgAttribute(): ?float
    {
        if ($this->weight_kg <= 0) {
            return null;
        }

        $totalCost = $this->total_cost;
        if ($totalCost === null) {
            return null;
        }

        return $totalCost / $this->weight_kg;
    }

    /**
     * Calcular el coste total de este output
     */
    public function getTotalCostAttribute(): ?float
    {
        // Prevenir recursión infinita usando pila
        if (in_array($this->id, self::$costCalculationStack)) {
            return null; // Ya visitado, retornar null para evitar ciclo
        }
        
        // Agregar a la pila
        self::$costCalculationStack[] = $this->id;
        $isRootCall = count(self::$costCalculationStack) === 1;

        try {
            // 1. Coste de materias primas (desde sources, recursivamente)
            $materialsCost = $this->calculateMaterialsCost();

            // 2. Costes del proceso (solo para outputs de ese proceso)
            $processCost = $this->calculateProcessCost();

            // 3. Costes del lote (solo para outputs finales)
            $productionCost = $this->calculateProductionCost();

            $total = $materialsCost + $processCost + $productionCost;

            return $total > 0 ? $total : null;
        } finally {
            // Remover de la pila
            array_pop(self::$costCalculationStack);
            
            // Si era la llamada raíz, limpiar completamente la pila (por si acaso)
            if ($isRootCall) {
                self::$costCalculationStack = [];
            }
        }
    }

    /**
     * Calcular coste de materias primas desde sources
     */
    protected function calculateMaterialsCost(): float
    {
        $total = 0;

        // Cargar sources si no están cargados
        if (!$this->relationLoaded('sources')) {
            $this->load('sources');
        }

        foreach ($this->sources as $source) {
            $sourceCost = $source->source_total_cost;
            if ($sourceCost !== null) {
                $total += $sourceCost;
            } elseif ($source->source_type === ProductionOutputSource::SOURCE_TYPE_PARENT_OUTPUT) {
                // Si el coste no está disponible, intentar calcularlo recursivamente
                if (!$source->relationLoaded('productionOutputConsumption') && $source->production_output_consumption_id) {
                    $source->load('productionOutputConsumption.productionOutput');
                }
                $consumption = $source->productionOutputConsumption;
                if ($consumption && $consumption->productionOutput) {
                    $parentOutput = $consumption->productionOutput;
                    
                    // Prevenir recursión infinita: si el output padre es el mismo que el actual, saltar
                    if ($parentOutput->id == $this->id) {
                        continue; // Ciclo detectado, saltar este source
                    }
                    
                    // Calcular coste del output padre recursivamente usando getTotalCostAttribute
                    // La protección contra recursión se maneja en getTotalCostAttribute
                    $parentTotalCost = $parentOutput->total_cost;
                    
                    if ($parentTotalCost !== null && $parentTotalCost > 0 && $parentOutput->weight_kg > 0) {
                        $parentCostPerKg = $parentTotalCost / $parentOutput->weight_kg;
                        $sourceWeight = $source->contributed_weight_kg ?? 0;
                        if ($sourceWeight > 0) {
                            $total += $sourceWeight * $parentCostPerKg;
                        }
                    }
                }
            }
        }

        return $total;
    }

    /**
     * Calcular costes del proceso (distribuidos proporcionalmente)
     */
    protected function calculateProcessCost(): float
    {
        $record = $this->productionRecord;
        if (!$record) {
            return 0;
        }

        // Obtener costes del proceso
        $processCosts = ProductionCost::where('production_record_id', $record->id)
            ->whereNull('production_id')
            ->get();

        if ($processCosts->isEmpty()) {
            return 0;
        }

        // Calcular peso total de outputs del proceso
        $totalOutputWeight = $record->total_output_weight;
        if ($totalOutputWeight <= 0) {
            return 0;
        }

        // Calcular coste total del proceso
        $totalProcessCost = 0;
        foreach ($processCosts as $cost) {
            $totalProcessCost += $cost->effective_total_cost ?? 0;
        }

        // Distribuir proporcionalmente según el peso de este output
        $outputPercentage = ($this->weight_kg / $totalOutputWeight) * 100;
        return ($totalProcessCost * $outputPercentage) / 100;
    }

    /**
     * Calcular costes del lote (solo para outputs finales)
     */
    protected function calculateProductionCost(): float
    {
        $record = $this->productionRecord;
        if (!$record) {
            return 0;
        }

        $production = $record->production;
        if (!$production) {
            return 0;
        }

        // Verificar si este output es de un nodo final
        if (!$this->isFinalOutput()) {
            return 0;
        }

        // Obtener costes del lote
        $productionCosts = ProductionCost::where('production_id', $production->id)
            ->whereNull('production_record_id')
            ->get();

        if ($productionCosts->isEmpty()) {
            return 0;
        }

        // Obtener outputs finales del lote
        $finalOutputs = $this->getFinalOutputsOfProduction($production);
        $totalFinalOutputWeight = $finalOutputs->sum('weight_kg');

        if ($totalFinalOutputWeight <= 0) {
            return 0;
        }

        // Calcular coste total del lote
        $totalProductionCost = 0;
        foreach ($productionCosts as $cost) {
            $totalProductionCost += $cost->effective_total_cost ?? 0;
        }

        // Distribuir proporcionalmente según el peso de este output final
        $outputPercentage = ($this->weight_kg / $totalFinalOutputWeight) * 100;
        return ($totalProductionCost * $outputPercentage) / 100;
    }

    /**
     * Verificar si este output es de un nodo final
     */
    protected function isFinalOutput(): bool
    {
        $record = $this->productionRecord;
        if (!$record) {
            return false;
        }

        // Un nodo final no tiene hijos y tiene outputs
        return $record->isFinal();
    }

    /**
     * Obtener outputs finales del lote
     */
    protected function getFinalOutputsOfProduction(Production $production)
    {
        // Obtener todos los records de esta producción con relaciones cargadas
        $allRecords = $production->records()
            ->with(['inputs', 'children', 'outputs'])
            ->get();
        
        // Filtrar solo los que son nodos finales
        $finalRecords = $allRecords->filter(function ($record) {
            return $record->isFinal();
        });
        
        // Obtener todos los outputs de los nodos finales
        $finalOutputs = collect();
        foreach ($finalRecords as $record) {
            $finalOutputs = $finalOutputs->merge($record->outputs);
        }
        
        return $finalOutputs;
    }

    /**
     * Obtener desglose completo de costes
     */
    public function getCostBreakdownAttribute(): array
    {
        $breakdown = [
            'materials' => [
                'total_cost' => 0,
                'cost_per_kg' => 0,
                'sources' => [],
            ],
            'process_costs' => [
                'production' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'labor' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'operational' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'packaging' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'total' => ['total_cost' => 0, 'cost_per_kg' => 0],
            ],
            'production_costs' => [
                'production' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'labor' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'operational' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'packaging' => ['total_cost' => 0, 'cost_per_kg' => 0, 'breakdown' => []],
                'total' => ['total_cost' => 0, 'cost_per_kg' => 0],
            ],
            'total' => [
                'total_cost' => 0,
                'cost_per_kg' => 0,
            ],
        ];

        // 1. Costes de materias primas
        // Cargar sources si no están cargados
        if (!$this->relationLoaded('sources')) {
            $this->load('sources');
        }

        $materialsCost = $this->calculateMaterialsCost();
        $breakdown['materials']['total_cost'] = $materialsCost;
        $breakdown['materials']['cost_per_kg'] = $this->weight_kg > 0 ? $materialsCost / $this->weight_kg : 0;

        foreach ($this->sources as $source) {
            $breakdown['materials']['sources'][] = [
                'source_type' => $source->source_type,
                'contributed_weight_kg' => $source->contributed_weight_kg,
                'contribution_percentage' => $source->contribution_percentage,
                'source_cost_per_kg' => $source->source_cost_per_kg,
                'source_total_cost' => $source->source_total_cost,
            ];
        }

        // 2. Costes del proceso
        $record = $this->productionRecord;
        if ($record) {
            $processCosts = ProductionCost::where('production_record_id', $record->id)
                ->whereNull('production_id')
                ->get();

            $totalOutputWeight = $record->total_output_weight;
            if ($totalOutputWeight > 0) {
                $outputPercentage = ($this->weight_kg / $totalOutputWeight) * 100;

                foreach ($processCosts as $cost) {
                    $costType = $cost->cost_type;
                    $effectiveCost = $cost->effective_total_cost ?? 0;
                    $assignedCost = ($effectiveCost * $outputPercentage) / 100;

                    $breakdown['process_costs'][$costType]['total_cost'] += $assignedCost;
                    $breakdown['process_costs'][$costType]['breakdown'][] = [
                        'name' => $cost->name,
                        'total_cost' => $assignedCost,
                        'cost_per_kg' => $this->weight_kg > 0 ? $assignedCost / $this->weight_kg : 0,
                    ];
                }

                // Calcular totales por tipo
                foreach (['production', 'labor', 'operational', 'packaging'] as $type) {
                    $breakdown['process_costs'][$type]['cost_per_kg'] = 
                        $this->weight_kg > 0 
                            ? $breakdown['process_costs'][$type]['total_cost'] / $this->weight_kg 
                            : 0;
                }

                // Total de costes del proceso
                $totalProcessCost = $breakdown['process_costs']['production']['total_cost'] +
                                   $breakdown['process_costs']['labor']['total_cost'] +
                                   $breakdown['process_costs']['operational']['total_cost'] +
                                   $breakdown['process_costs']['packaging']['total_cost'];
                $breakdown['process_costs']['total']['total_cost'] = $totalProcessCost;
                $breakdown['process_costs']['total']['cost_per_kg'] = 
                    $this->weight_kg > 0 ? $totalProcessCost / $this->weight_kg : 0;
            }
        }

        // 3. Costes del lote (solo para outputs finales)
        if ($this->isFinalOutput() && $record) {
            $production = $record->production;
            if ($production) {
                $productionCosts = ProductionCost::where('production_id', $production->id)
                    ->whereNull('production_record_id')
                    ->get();

                $finalOutputs = $this->getFinalOutputsOfProduction($production);
                $totalFinalOutputWeight = $finalOutputs->sum('weight_kg');

                if ($totalFinalOutputWeight > 0) {
                    $outputPercentage = ($this->weight_kg / $totalFinalOutputWeight) * 100;

                    foreach ($productionCosts as $cost) {
                        $costType = $cost->cost_type;
                        $effectiveCost = $cost->effective_total_cost ?? 0;
                        $assignedCost = ($effectiveCost * $outputPercentage) / 100;

                        $breakdown['production_costs'][$costType]['total_cost'] += $assignedCost;
                        $breakdown['production_costs'][$costType]['breakdown'][] = [
                            'name' => $cost->name,
                            'total_cost' => $assignedCost,
                            'cost_per_kg' => $this->weight_kg > 0 ? $assignedCost / $this->weight_kg : 0,
                        ];
                    }

                    // Calcular totales por tipo
                    foreach (['production', 'labor', 'operational', 'packaging'] as $type) {
                        $breakdown['production_costs'][$type]['cost_per_kg'] = 
                            $this->weight_kg > 0 
                                ? $breakdown['production_costs'][$type]['total_cost'] / $this->weight_kg 
                                : 0;
                    }

                    // Total de costes del lote
                    $totalProductionCost = $breakdown['production_costs']['production']['total_cost'] +
                                          $breakdown['production_costs']['labor']['total_cost'] +
                                          $breakdown['production_costs']['operational']['total_cost'] +
                                          $breakdown['production_costs']['packaging']['total_cost'];
                    $breakdown['production_costs']['total']['total_cost'] = $totalProductionCost;
                    $breakdown['production_costs']['total']['cost_per_kg'] = 
                        $this->weight_kg > 0 ? $totalProductionCost / $this->weight_kg : 0;
                }
            }
        }

        // 4. Total general
        $totalCost = $materialsCost + 
                    $breakdown['process_costs']['total']['total_cost'] + 
                    $breakdown['production_costs']['total']['total_cost'];
        
        $breakdown['total']['total_cost'] = $totalCost;
        $breakdown['total']['cost_per_kg'] = $this->weight_kg > 0 ? $totalCost / $this->weight_kg : 0;

        return $breakdown;
    }
}
