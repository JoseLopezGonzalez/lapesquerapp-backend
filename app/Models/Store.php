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

    protected $fillable = ['name', 'category_id', 'temperature', 'capacity', 'map'];
    //protected $table = 'stores';

    public function categoria()
    {
        return $this->belongsTo(ArticleCategory::class, 'category_id');
    }

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
        //$netWeightPallets = 0;

        return $this->pallets->reduce(function ($carry, $pallet) {
            return $carry + $pallet->pallet->netWeight;
        }, 0);

        /*  $this->pallets->map(function ($pallet) {
             global $netWeightPallets;
             $netWeightPallets += $pallet->netWeight;
         }); */



        /* foreach ($this->pallets as $pallet) {
            $netWeightPallets += $pallet->netWeight; //Implementar atributo accesor en pallet pesoNeto
        } */
        //return $netWeightPallets;
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
                'pallets' => $this->pallets->map(function ($storedPallet) {
                    // Asegurarnos de que el pallet no intente resolver la relación 'state'
                    $pallet = $storedPallet->pallet;
                    if ($pallet) {
                        // Establecer explícitamente que 'state' y 'palletState' no son relaciones
                        $pallet->setRelation('state', null);
                        $pallet->setRelation('palletState', null);
                        if ($pallet->relationLoaded('state')) {
                            $pallet->unsetRelation('state');
                        }
                        if ($pallet->relationLoaded('palletState')) {
                            $pallet->unsetRelation('palletState');
                        }
                    }
                    return $storedPallet->toArrayAssoc();
                }),
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
