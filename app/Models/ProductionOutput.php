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
     * Calcular el peso promedio por caja
     */
    public function getAverageWeightPerBoxAttribute()
    {
        if ($this->boxes > 0) {
            return $this->weight_kg / $this->boxes;
        }
        return 0;
    }
}
