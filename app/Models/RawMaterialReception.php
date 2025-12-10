<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterialReception extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = ['supplier_id', 'date', 'notes', 'declared_total_amount', 'declared_total_net_weight', 'creation_mode'];

    // Constantes para creation_mode
    const CREATION_MODE_LINES = 'lines';
    const CREATION_MODE_PALLETS = 'pallets';

    protected $appends = ['total_amount', 'can_edit', 'cannot_edit_reason'];

    /* hacer numeros  declared_total_amount y declared_total_net_weight*/
    
    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();
  
        // Validación antes de eliminar
        static::deleting(function ($reception) {
            foreach ($reception->pallets as $pallet) {
                // Validar que el palet no esté en uso
                if ($pallet->order_id !== null) {
                    throw new \Exception("No se puede eliminar la recepción: el palet #{$pallet->id} está vinculado a un pedido");
                }
          
                if ($pallet->status === Pallet::STATE_STORED) {
                    throw new \Exception("No se puede eliminar la recepción: el palet #{$pallet->id} está almacenado");
                }
          
                // Validar que las cajas no estén en producción
                foreach ($pallet->boxes as $palletBox) {
                    if ($palletBox->box->productionInputs()->exists()) {
                        throw new \Exception("No se puede eliminar la recepción: la caja #{$palletBox->box->id} está siendo usada en producción");
                    }
                }
            }
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function products()
    {
        return $this->hasMany(RawMaterialReceptionProduct::class, 'reception_id');
    }

    /**
     * Relación con palets creados desde esta recepción
     */
    public function pallets()
    {
        return $this->hasMany(Pallet::class, 'reception_id');
    }

    public function getNetWeightAttribute()
    {
        return $this->products->sum('net_weight');
    }


    /* GEnerar atributo especie segun la especie a la que pertenezca sus productos */
    public function getSpeciesAttribute()
    {
        return $this->products->first()->product->species;
    }

    public function getTotalAmountAttribute()
    {
        return $this->products->sum(function ($product) {
            return ($product->net_weight ?? 0) * ($product->price ?? 0);
        });
    }

    /**
     * Verificar si la recepción se puede editar
     * No se puede editar si:
     * - Alguna caja está siendo usada en producción
     * - Algún palet está vinculado a un pedido
     */
    public function getCanEditAttribute(): bool
    {
        // Cargar relaciones si no están cargadas
        if (!$this->relationLoaded('pallets')) {
            $this->load('pallets.boxes.box.productionInputs');
        }

        foreach ($this->pallets as $pallet) {
            // Verificar si el palet está vinculado a un pedido
            if ($pallet->order_id !== null) {
                return false;
            }

            // Verificar si alguna caja está en producción
            foreach ($pallet->boxes as $palletBox) {
                if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Obtener la razón por la que no se puede editar
     */
    public function getCannotEditReasonAttribute(): ?string
    {
        if ($this->can_edit) {
            return null;
        }

        // Cargar relaciones si no están cargadas
        if (!$this->relationLoaded('pallets')) {
            $this->load('pallets.boxes.box.productionInputs');
        }

        foreach ($this->pallets as $pallet) {
            // Verificar si el palet está vinculado a un pedido
            if ($pallet->order_id !== null) {
                return "El palet #{$pallet->id} está vinculado a un pedido";
            }

            // Verificar si alguna caja está en producción
            foreach ($pallet->boxes as $palletBox) {
                if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                    return "La caja #{$palletBox->box->id} está siendo usada en producción";
                }
            }
        }

        return "No se puede editar la recepción";
    }

}
