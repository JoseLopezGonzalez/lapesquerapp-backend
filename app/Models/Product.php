<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'name', // Campo real ahora (no accessor)
        'family_id',
        'species_id',
        'capture_zone_id',
        'article_gtin',
        'box_gtin',
        'pallet_gtin',
        'a3erp_code',
        'facil_com_code',
    ];

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
        return [
            'id' => $this->id,
            'name' => $this->name, // Campo directo desde BD
            'species' => $this->species ? ($this->species->toArrayAssoc() ?? []) : [],
            'captureZone' => $this->captureZone ? ($this->captureZone->toArrayAssoc() ?? []) : [],
            'category' => ($this->family && $this->family->category) ? ($this->family->category->toArrayAssoc() ?? []) : [],
            'family' => $this->family ? ($this->family->toArrayAssoc() ?? []) : [],
            'articleGtin' => $this->article_gtin,
            'boxGtin' => $this->box_gtin,
            'palletGtin' => $this->pallet_gtin,
            'a3erpCode' => $this->a3erp_code,
            'facilcomCode' => $this->facil_com_code,
        ];
    }

    public function productionNodes()
    {
        return $this->belongsToMany(ProductionNode::class, 'production_node_product')->withPivot('quantity');
    }

    public function rawMaterials()
    {
        return $this->has(RawMaterial::class, 'id');
    }

    /**
     * Boot del modelo - Validaciones básicas
     * Nota: Las validaciones de unicidad se manejan en el controlador
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($product) {
            // Validar name no vacío
            if (empty($product->name)) {
                throw ValidationException::withMessages([
                    'name' => 'El nombre del producto no puede estar vacío.',
                ]);
            }

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
        });
    }




}
