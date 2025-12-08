<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Pallet;

class StoredPallet extends Model
{
    use UsesTenantConnection;
    // App\Models\StoredPallet.php
    protected $fillable = ['pallet_id', 'store_id', 'position']; // si usas 'position' también


    use HasFactory;
    //protected $table = 'pallet_positions_store';

    public function pallet()
    {
        return $this->belongsTo(Pallet::class, 'pallet_id');
    }

    /* has store */
    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function toArrayAssoc()
    {
        return array_merge($this->pallet->toArrayAssoc(), [
            //'storeId' => $this->store_id,
            'position' => $this->position,
        ]);
    }


    /* NUEVO LA PESQUERAPP */

public function scopeStored($query)
{
    return $query
        ->join('pallets', 'pallets.id', '=', 'stored_pallets.pallet_id')
        ->where('pallets.status', Pallet::STATE_STORED);
}

}
