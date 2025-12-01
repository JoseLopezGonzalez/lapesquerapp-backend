<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOutputConsumption extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'production_record_id',
        'production_output_id',
        'consumed_weight_kg',
        'consumed_boxes',
        'notes',
    ];

    protected $casts = [
        'consumed_weight_kg' => 'decimal:2',
        'consumed_boxes' => 'integer',
    ];

    /**
     * Relación con ProductionRecord (proceso que consume)
     */
    public function productionRecord()
    {
        return $this->belongsTo(ProductionRecord::class, 'production_record_id');
    }

    /**
     * Relación con ProductionOutput (output del padre que se consume)
     */
    public function productionOutput()
    {
        return $this->belongsTo(ProductionOutput::class, 'production_output_id');
    }

    /**
     * Obtener el producto desde el output
     */
    public function getProductAttribute()
    {
        return $this->productionOutput->product ?? null;
    }

    /**
     * Obtener el proceso padre desde el output
     */
    public function getParentRecordAttribute()
    {
        return $this->productionOutput->productionRecord ?? null;
    }

    /**
     * Verificar si el consumo es completo (consume todo el output)
     */
    public function isComplete()
    {
        $output = $this->productionOutput;
        if (!$output) {
            return false;
        }

        $weightComplete = $this->consumed_weight_kg >= $output->weight_kg;
        $boxesComplete = $this->consumed_boxes >= $output->boxes;

        return $weightComplete && $boxesComplete;
    }

    /**
     * Verificar si el consumo es parcial
     */
    public function isPartial()
    {
        return !$this->isComplete();
    }

    /**
     * Calcular el porcentaje de consumo por peso
     */
    public function getWeightConsumptionPercentageAttribute()
    {
        $output = $this->productionOutput;
        if (!$output || $output->weight_kg <= 0) {
            return 0;
        }

        return ($this->consumed_weight_kg / $output->weight_kg) * 100;
    }

    /**
     * Calcular el porcentaje de consumo por cajas
     */
    public function getBoxesConsumptionPercentageAttribute()
    {
        $output = $this->productionOutput;
        if (!$output || $output->boxes <= 0) {
            return 0;
        }

        return ($this->consumed_boxes / $output->boxes) * 100;
    }
}

