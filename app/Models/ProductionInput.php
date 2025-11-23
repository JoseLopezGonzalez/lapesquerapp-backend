<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionInput extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'production_record_id',
        'box_id',
    ];

    /**
     * Relación con ProductionRecord (proceso)
     */
    public function productionRecord()
    {
        return $this->belongsTo(ProductionRecord::class, 'production_record_id');
    }

    /**
     * Relación con Box (caja individual)
     */
    public function box()
    {
        return $this->belongsTo(Box::class, 'box_id');
    }

    /**
     * Obtener el producto desde la caja
     */
    public function getProductAttribute()
    {
        return $this->box->product ?? null;
    }

    /**
     * Obtener el lote desde la caja
     */
    public function getLotAttribute()
    {
        return $this->box->lot ?? null;
    }

    /**
     * Obtener el peso desde la caja
     */
    public function getWeightAttribute()
    {
        return $this->box->net_weight ?? 0;
    }

    /**
     * Obtener el palet desde la caja
     */
    public function getPalletAttribute()
    {
        return $this->box->pallet ?? null;
    }
}
