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
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Validar antes de crear
        static::creating(function ($input) {
            $input->validateCreationRules();
        });
    }

    /**
     * Validar reglas al crear ProductionInput
     */
    protected function validateCreationRules(): void
    {
        // Validar que la caja exista y no esté eliminada
        $box = Box::find($this->box_id);
        if (!$box) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "La caja con ID {$this->box_id} no existe."
            );
        }

        // Validar que la caja esté disponible (no usada en otros procesos)
        if (!$box->isAvailable) {
            throw new \InvalidArgumentException(
                "La caja con ID {$this->box_id} ya está siendo usada en otro proceso de producción y no está disponible."
            );
        }

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
                'No se pueden agregar entradas a procesos de lotes cerrados. El lote debe estar abierto (closed_at = null).'
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
