<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RawMaterialReceptionProduct extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = ['reception_id', 'product_id', 'net_weight' , 'price'];

    public function reception()
    {
        
        return $this->belongsTo(RawMaterialReception::class, 'reception_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /* Alias Attribute from RawMaterial atribute that coincida con el id de producto */
    public function getAliasAttribute()
    {
        /* Find RawMaterial(product_id) */
        $rawMaterial = RawMaterial::where('id', $this->product_id)->first();
        return $rawMaterial->alias;
        /* return $this->product->rawMaterials->where('id', $this->product_id)->first()->alias; */
    }

    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($product) {
            // Validar net_weight > 0
            if ($product->net_weight !== null && $product->net_weight <= 0) {
                throw ValidationException::withMessages([
                    'net_weight' => 'El peso neto debe ser mayor que 0.',
                ]);
            }

            // Validar price ≥ 0 (si existe)
            if ($product->price !== null && $product->price < 0) {
                throw ValidationException::withMessages([
                    'price' => 'El precio no puede ser negativo.',
                ]);
            }
        });
    }

}
