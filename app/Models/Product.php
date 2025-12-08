<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Article;
use App\Models\Species;
use App\Models\CaptureZone;

use App\Models\ProductFamily;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    //protected $table = 'products';
    /* fillable */
    protected $fillable = [
        'id',
        'article_id', //Esto 
        'family_id',
        'species_id',
        'capture_zone_id',
        'article_gtin',
        'box_gtin',
        'pallet_gtin',
        'fixed_weight',
        'name',
        'a3erp_code',
        'facil_com_code',
    ];

    /*  public function article()
     {
         return $this->belongsTo(Article::class, 'id'); // No se bien porque no indica que el id es el que relaciona las tablas
     } */

    public function species()
    {
        return $this->belongsTo(Species::class, 'species_id'); // No se bien porque no indica que el id es el que relaciona las tablas
    }

    public function captureZone()
    {
        return $this->belongsTo(CaptureZone::class, 'capture_zone_id'); // No se bien porque no indica que el id es el que relaciona las tablas
    }



    public function family()
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }

    public function toArrayAssoc()
    {
        return array_merge(
            $this->article ? ($this->article->toArrayAssoc() ?? []) : [],
            [
                'species' => $this->species ? ($this->species->toArrayAssoc() ?? []) : [],
                'captureZone' => $this->captureZone ? ($this->captureZone->toArrayAssoc() ?? []) : [],
                'category' => ($this->family && $this->family->category) ? ($this->family->category->toArrayAssoc() ?? []) : [],
                'family' => $this->family ? ($this->family->toArrayAssoc() ?? []) : [],
                'articleGtin' => $this->article_gtin,
                'boxGtin' => $this->box_gtin,
                'palletGtin' => $this->pallet_gtin,
                'fixedWeight' => $this->fixed_weight,
                'name' => $this->name,
                'id' => $this->id,
                'a3erpCode' => $this->a3erp_code,
                'facilcomCode' => $this->facil_com_code,
            ]
        );
    }


    /* name attribute */
    public function getNameAttribute()
    {
        return $this->article ? $this->article->name : null;
    }

    public function productionNodes()
    {
        return $this->belongsToMany(ProductionNode::class, 'production_node_product')->withPivot('quantity');
    }

    public function rawMaterials()
    {
        return $this->has(RawMaterial::class, 'id');
    }


    public function article()
    {
        return $this->belongsTo(Article::class, 'id', 'id');
    }

    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($product) {
            // Validar species_id y capture_zone_id requeridos
            if (!$product->species_id) {
                throw ValidationException::withMessages([
                    'species_id' => 'El campo species_id es requerido.',
                ]);
            }

            if (!$product->capture_zone_id) {
                throw ValidationException::withMessages([
                    'capture_zone_id' => 'El campo capture_zone_id es requerido.',
                ]);
            }

            // Validar que name no esté vacío (desde Article)
            if ($product->article && empty($product->article->name)) {
                throw ValidationException::withMessages([
                    'name' => 'El nombre del producto no puede estar vacío.',
                ]);
            }

            // Validar GTINs únicos (si tienen valor)
            if ($product->article_gtin) {
                $existing = self::where('article_gtin', $product->article_gtin)
                    ->where('id', '!=', $product->id ?? 0)
                    ->first();
                
                if ($existing) {
                    throw ValidationException::withMessages([
                        'article_gtin' => 'Ya existe un producto con este article_gtin.',
                    ]);
                }
            }

            if ($product->box_gtin) {
                $existing = self::where('box_gtin', $product->box_gtin)
                    ->where('id', '!=', $product->id ?? 0)
                    ->first();
                
                if ($existing) {
                    throw ValidationException::withMessages([
                        'box_gtin' => 'Ya existe un producto con este box_gtin.',
                    ]);
                }
            }

            if ($product->pallet_gtin) {
                $existing = self::where('pallet_gtin', $product->pallet_gtin)
                    ->where('id', '!=', $product->id ?? 0)
                    ->first();
                
                if ($existing) {
                    throw ValidationException::withMessages([
                        'pallet_gtin' => 'Ya existe un producto con este pallet_gtin.',
                    ]);
                }
            }
        });
    }




}
