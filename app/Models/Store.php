<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Pallet;

class Store extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = ['name', 'temperature', 'capacity', 'map'];
    //protected $table = 'stores';

    // Definir relación con Pallet
    /* public function pallets()
    {
        return $this->hasMany(Pallet::class, 'store_id');
    } */

    // Definir relación con Pallet
    public function pallets()
    {
        //dd($this->hasMany(StoredPallet::class, 'store_id'));
        return $this->hasMany(StoredPallet::class, 'store_id');
    }

    public function palletsV2()
    {
        return $this->belongsToMany(Pallet::class, 'stored_pallets', 'store_id', 'pallet_id')
            ->withPivot('position');
    }


    //Accessor 
    public function getNetWeightPalletsAttribute()
    {
        if (!$this->relationLoaded('palletsV2') || !$this->palletsV2) {
            return 0;
        }
        return $this->palletsV2->sum(function ($pallet) {
            return $pallet->netWeight ?? 0;
        });
    }

    //Accessor 
    public function getNetWeightBoxesAttribute()
    {
        //Implementar...
        return 0;
    }

    //Accessor 
    public function getNetWeightBigBoxesAttribute()
    {
        //Implementar...
        return 0;
    }

    public function getTotalNetWeightAttribute() //No se bien si llamarlo simplemente pesoNeto
    {
        return $this->netWeightPallets + $this->netWeightBigBoxes + $this->netWeightBoxes;
    }

    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'temperature' => $this->temperature,
            'capacity' => $this->capacity,
            'netWeightPallets' => $this->netWeightPallets,
            'totalNetWeight' => $this->totalNetWeight,
            'content' => [
                'pallets' => [], // No incluir contenido completo en listado (solo en show/detalle)
                'boxes' => [],
                'bigBoxes' => [],
            ],
            'map' => json_decode($this->map, true),
        ];
    }

    public function toSimpleArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'temperature' => $this->temperature,
            'capacity' => $this->capacity,
            'netWeightPallets' => $this->netWeightPallets,
            'totalNetWeight' => $this->totalNetWeight,
        ];
    }



    


}
