<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
        try {
            Log::info('🟡 [STORE MODEL] getNetWeightPalletsAttribute llamado', [
                'store_id' => $this->id,
                'relation_loaded' => $this->relationLoaded('palletsV2'),
                'palletsV2_exists' => isset($this->palletsV2),
                'palletsV2_is_null' => is_null($this->palletsV2)
            ]);

            if (!$this->relationLoaded('palletsV2') || !$this->palletsV2) {
                Log::info('🟡 [STORE MODEL] getNetWeightPalletsAttribute: Retornando 0 (relación no cargada o null)');
                return 0;
            }

            Log::info('🟡 [STORE MODEL] getNetWeightPalletsAttribute: Calculando suma', [
                'pallets_count' => $this->palletsV2->count()
            ]);

            $sum = $this->palletsV2->sum(function ($pallet) {
                return $pallet->netWeight ?? 0;
            });

            Log::info('🟡 [STORE MODEL] getNetWeightPalletsAttribute: Suma calculada', ['sum' => $sum]);
            return $sum;
        } catch (\Exception $e) {
            Log::error('🔴 [STORE MODEL] Error en getNetWeightPalletsAttribute', [
                'store_id' => $this->id ?? 'N/A',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 0;
        }
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
        try {
            Log::info('🟡 [STORE MODEL] getTotalNetWeightAttribute llamado', ['store_id' => $this->id]);
            $total = $this->netWeightPallets + $this->netWeightBigBoxes + $this->netWeightBoxes;
            Log::info('🟡 [STORE MODEL] getTotalNetWeightAttribute calculado', ['total' => $total]);
            return $total;
        } catch (\Exception $e) {
            Log::error('🔴 [STORE MODEL] Error en getTotalNetWeightAttribute', [
                'store_id' => $this->id ?? 'N/A',
                'message' => $e->getMessage()
            ]);
            return 0;
        }
    }

    public function toArrayAssoc()
    {
        try {
            Log::info('🟢 [STORE MODEL] toArrayAssoc iniciado', [
                'store_id' => $this->id,
                'store_name' => $this->name
            ]);

            $id = $this->id;
            $name = $this->name;
            $temperature = $this->temperature;
            $capacity = $this->capacity;
            
            Log::info('🟢 [STORE MODEL] toArrayAssoc: Campos básicos obtenidos');

            Log::info('🟢 [STORE MODEL] toArrayAssoc: Llamando getNetWeightPalletsAttribute');
            $netWeightPallets = $this->netWeightPallets;
            Log::info('🟢 [STORE MODEL] toArrayAssoc: netWeightPallets obtenido', ['value' => $netWeightPallets]);

            Log::info('🟢 [STORE MODEL] toArrayAssoc: Llamando getTotalNetWeightAttribute');
            $totalNetWeight = $this->totalNetWeight;
            Log::info('🟢 [STORE MODEL] toArrayAssoc: totalNetWeight obtenido', ['value' => $totalNetWeight]);

            Log::info('🟢 [STORE MODEL] toArrayAssoc: Procesando palletsV2', [
                'relation_loaded' => $this->relationLoaded('palletsV2'),
                'palletsV2_exists' => isset($this->palletsV2),
                'palletsV2_count' => $this->relationLoaded('palletsV2') && $this->palletsV2 ? $this->palletsV2->count() : 0
            ]);

            $pallets = [];
            if ($this->relationLoaded('palletsV2') && $this->palletsV2) {
                Log::info('🟢 [STORE MODEL] toArrayAssoc: Mapeando pallets');
                $pallets = $this->palletsV2->map(function ($pallet) {
                    try {
                        Log::info('🟢 [STORE MODEL] toArrayAssoc: Procesando pallet', ['pallet_id' => $pallet->id ?? 'N/A']);
                        $result = $pallet->toArrayAssocV2();
                        Log::info('🟢 [STORE MODEL] toArrayAssoc: Pallet procesado exitosamente', ['pallet_id' => $pallet->id ?? 'N/A']);
                        return $result;
                    } catch (\Exception $e) {
                        Log::error('🔴 [STORE MODEL] Error procesando pallet en toArrayAssoc', [
                            'pallet_id' => $pallet->id ?? 'N/A',
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                        return null;
                    }
                })->filter()->values();
                Log::info('🟢 [STORE MODEL] toArrayAssoc: Pallets mapeados', ['count' => $pallets->count()]);
            } else {
                Log::info('🟢 [STORE MODEL] toArrayAssoc: Pallets vacío (relación no cargada o null)');
            }

            Log::info('🟢 [STORE MODEL] toArrayAssoc: Decodificando map');
            $map = json_decode($this->map, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('🟡 [STORE MODEL] toArrayAssoc: Error decodificando map', [
                    'json_error' => json_last_error_msg(),
                    'map_raw' => $this->map
                ]);
                $map = null;
            }

            $result = [
                'id' => $id,
                'name' => $name,
                'temperature' => $temperature,
                'capacity' => $capacity,
                'netWeightPallets' => $netWeightPallets,
                'totalNetWeight' => $totalNetWeight,
                'content' => [
                    'pallets' => $pallets,
                    'boxes' => [],
                    'bigBoxes' => [],
                ],
                'map' => $map,
            ];

            Log::info('🟢 [STORE MODEL] toArrayAssoc completado exitosamente', ['store_id' => $this->id]);
            return $result;
        } catch (\Exception $e) {
            Log::error('🔴 [STORE MODEL] Error en toArrayAssoc', [
                'store_id' => $this->id ?? 'N/A',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
