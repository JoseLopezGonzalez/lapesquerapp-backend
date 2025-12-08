<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PalletBox extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    /* Fillable */
    protected $fillable = [
        'box_id',
        'pallet_id',
        'lot',
        'net_weight',
        'article_id'
    ];



    public function box()
    {
        return $this->belongsTo(Box::class, 'box_id');
    }

    public function pallet()
    {
        return $this->belongsTo(Pallet::class, 'pallet_id');
    }

    public function toArrayAssoc()
    {
        return $this->box ? $this->box->toArrayAssoc() : null;
    }

    public function toArrayAssocV2()
    {
        return $this->box ? $this->box->toArrayAssocV2() : null;
    }

    public function getNetWeightAttribute()
    {
        return $this->box->net_weight;
    }

    public function delete()
    {

        $this->box->delete();

        parent::delete();
    }
}
