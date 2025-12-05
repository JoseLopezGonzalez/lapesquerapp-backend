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
}
