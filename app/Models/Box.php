<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    //protected $table = 'boxes';

    protected $fillable = ['article_id', 'lot', 'gs1_128', 'gross_weight', 'net_weight'];

    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Validar antes de guardar
        static::saving(function ($box) {
            $box->validateBoxRules();
        });

        // Validar antes de eliminar
        static::deleting(function ($box) {
            $box->validateDeletionRules();
        });
    }

    /**
     * Validar reglas de Box al guardar
     */
    protected function validateBoxRules(): void
    {
        // Validar que net_weight > 0
        if ($this->net_weight !== null && $this->net_weight <= 0) {
            throw new \InvalidArgumentException(
                'El peso neto (net_weight) debe ser mayor que 0.'
            );
        }

        // Validar que gross_weight >= net_weight (si ambos existen)
        if ($this->gross_weight !== null && $this->net_weight !== null) {
            if ($this->gross_weight < $this->net_weight) {
                throw new \InvalidArgumentException(
                    'El peso bruto (gross_weight) no puede ser menor que el peso neto (net_weight).'
                );
            }
        }

        // Validar que lot no esté vacío
        if ($this->lot !== null && trim($this->lot) === '') {
            throw new \InvalidArgumentException(
                'El campo lote (lot) no puede estar vacío.'
            );
        }

        // Validar que article_id exista
        if ($this->article_id !== null) {
            $product = Product::find($this->article_id);
            if (!$product) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                    "El producto con ID {$this->article_id} no existe."
                );
            }
        }
    }

    /**
     * Validar reglas al eliminar Box
     */
    protected function validateDeletionRules(): void
    {
        // No permitir eliminar si tiene productionInputs (fue usada en producción)
        if ($this->productionInputs()->exists()) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'No se puede eliminar una caja que ha sido usada en producción. La caja debe mantenerse para trazabilidad histórica.'
            );
        }
    }

    //Alguna parte del codigo usa esto todavia aunque este mal semanticamente
    public function article()
    {
        return $this->belongsTo(Product::class, 'article_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'article_id');
    }

    public function palletBox()
    {
        return $this->hasOne(PalletBox::class, 'box_id');
    }

    public function getPalletAttribute()
    {
        /* Si no palletBox return null*/
        return $this->palletBox ? $this->palletBox->pallet : null;
    }

    /**
     * Relación con ProductionInput (en qué procesos de producción se ha usado esta caja)
     */
    public function productionInputs()
    {
        return $this->hasMany(ProductionInput::class, 'box_id');
    }

    /**
     * Determina si la caja está disponible (no ha sido usada en ningún proceso de producción)
     */
    public function getIsAvailableAttribute()
    {
        // Si la relación ya está cargada, usar exists() es más eficiente
        if ($this->relationLoaded('productionInputs')) {
            return $this->productionInputs->isEmpty();
        }
        
        // Si no está cargada, hacer una consulta directa más eficiente
        return !$this->productionInputs()->exists();
    }

    /**
     * Obtiene la producción más reciente en la que se ha usado esta caja
     * Retorna null si la caja no ha sido usada en ninguna producción
     */
    public function getProductionAttribute()
    {
        // Si la relación ya está cargada, usar la colección
        if ($this->relationLoaded('productionInputs')) {
            $latestInput = $this->productionInputs
                ->sortByDesc('created_at')
                ->first();
            
            if ($latestInput) {
                // Intentar obtener la producción desde el productionRecord cargado
                $productionRecord = $latestInput->productionRecord;
                if ($productionRecord && $productionRecord->relationLoaded('production')) {
                    return $productionRecord->production;
                }
            }
        }
        
        // Si no está cargada o las relaciones anidadas no están cargadas, hacer una consulta optimizada
        $latestInput = $this->productionInputs()
            ->with('productionRecord.production')
            ->orderBy('created_at', 'desc')
            ->first();
        
        return $latestInput?->productionRecord?->production;
    }

    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'palletId' => $this->pallet_id,
            'article' => $this->article->toArrayAssoc(),
            'lot' => $this->lot,
            'gs1128' => $this->gs1_128,
            'grossWeight' => $this->gross_weight,
            'netWeight' => $this->net_weight,
            'createdAt' => $this->created_at, //formatear para mostrar solo fecha
        ];
    }


    public function toArrayAssocV2()
    {
        $production = $this->production;
        
        return [
            'id' => $this->id,
            'palletId' => $this->pallet_id,
            'product' => $this->product->toArrayAssoc(), // Asegúrate que también esté limpio este método
            'lot' => $this->lot,
            'gs1128' => $this->gs1_128,
            'grossWeight' => (float) $this->gross_weight,
            'netWeight' => (float) $this->net_weight,
            'createdAt' => $this->created_at?->format('Y-m-d'), // Solo fecha
            'isAvailable' => $this->isAvailable, // Flag que indica si la caja está disponible (no usada en producción)
            'production' => $production ? [
                'id' => $production->id,
                'lot' => $production->lot,
            ] : null, // Información de la producción más reciente en la que se usó esta caja
        ];
    }

}
