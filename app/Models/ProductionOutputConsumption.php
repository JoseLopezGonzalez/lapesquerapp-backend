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
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Validar antes de guardar
        static::saving(function ($consumption) {
            $consumption->validateConsumptionRules();
        });

        // Validar antes de crear
        static::creating(function ($consumption) {
            $consumption->validateCreationRules();
        });
    }

    /**
     * Validar reglas de ProductionOutputConsumption al guardar
     */
    protected function validateConsumptionRules(): void
    {
        // Validar que consumed_weight_kg > 0
        if ($this->consumed_weight_kg <= 0) {
            throw new \InvalidArgumentException(
                'El peso consumido (consumed_weight_kg) debe ser mayor que 0.'
            );
        }

        // consumed_boxes puede ser 0 según las soluciones del usuario, pero si tiene valor debe ser >= 0
        if ($this->consumed_boxes < 0) {
            throw new \InvalidArgumentException(
                'El número de cajas consumidas (consumed_boxes) no puede ser negativo.'
            );
        }

        // Validar que el output pertenezca al padre del proceso
        if ($this->production_output_id && $this->production_record_id) {
            $productionRecord = ProductionRecord::find($this->production_record_id);
            $productionOutput = ProductionOutput::with('productionRecord')->find($this->production_output_id);

            if ($productionRecord && $productionOutput) {
                // El output debe pertenecer al proceso padre del proceso que consume
                if ($productionRecord->parent_record_id !== $productionOutput->production_record_id) {
                    throw new \InvalidArgumentException(
                        'El output consumido debe pertenecer al proceso padre del proceso que consume. Solo se pueden consumir outputs del proceso padre.'
                    );
                }
            }
        }

        // Validar que no se consuma más de lo disponible
        if ($this->production_output_id) {
            $output = ProductionOutput::find($this->production_output_id);
            if ($output) {
                // Calcular lo ya consumido (excluyendo este consumo si estamos actualizando)
                $existingConsumption = $this->exists 
                    ? static::where('production_output_id', $this->production_output_id)
                        ->where('id', '!=', $this->id)
                        ->sum('consumed_weight_kg')
                    : static::where('production_output_id', $this->production_output_id)
                        ->sum('consumed_weight_kg');

                $availableWeight = $output->weight_kg - $existingConsumption;

                if ($this->consumed_weight_kg > $availableWeight) {
                    throw new \InvalidArgumentException(
                        "No se puede consumir más peso del disponible. Peso disponible: {$availableWeight} kg, intentando consumir: {$this->consumed_weight_kg} kg."
                    );
                }

                // Validar cajas (solo si consumed_boxes > 0)
                if ($this->consumed_boxes > 0) {
                    $existingBoxes = $this->exists
                        ? static::where('production_output_id', $this->production_output_id)
                            ->where('id', '!=', $this->id)
                            ->sum('consumed_boxes')
                        : static::where('production_output_id', $this->production_output_id)
                            ->sum('consumed_boxes');

                    $availableBoxes = $output->boxes - $existingBoxes;

                    if ($this->consumed_boxes > $availableBoxes) {
                        throw new \InvalidArgumentException(
                            "No se pueden consumir más cajas de las disponibles. Cajas disponibles: {$availableBoxes}, intentando consumir: {$this->consumed_boxes}."
                        );
                    }
                }
            }
        }
    }

    /**
     * Validar reglas al crear ProductionOutputConsumption
     */
    protected function validateCreationRules(): void
    {
        // La validación principal está en validateConsumptionRules
        // Aquí podemos agregar validaciones específicas de creación si es necesario
    }

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

