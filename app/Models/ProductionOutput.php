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
