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
        return [
            'id' => $this->id,
            'palletId' => $this->pallet_id,
            'product' => $this->product->toArrayAssoc(), // Asegúrate que también esté limpio este método
            'lot' => $this->lot,
            'gs1128' => $this->gs1_128,
            'grossWeight' => (float) $this->gross_weight,
            'netWeight' => (float) $this->net_weight,
            'createdAt' => $this->created_at?->format('Y-m-d'), // Solo fecha
        ];
    }

}
