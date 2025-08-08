<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Article;
use App\Models\Species;
use App\Models\CaptureZone;

use App\Models\ProductFamily;

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
            optional($this->article)->toArrayAssoc() ?? [],
            [
                'species' => optional($this->species)->toArrayAssoc() ?? [],
                'captureZone' => optional($this->captureZone)->toArrayAssoc() ?? [],
                'category' => optional($this->family->category)->toArrayAssoc() ?? [],
                'family' => optional($this->family)->toArrayAssoc() ?? [],
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
        return $this->article->name;
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




}
