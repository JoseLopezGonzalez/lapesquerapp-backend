<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Pallet extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    /**
     * Estados fijos de los palets
     * Ya no dependen de la tabla pallet_states
     */
    const STATE_REGISTERED = 1;  // Registrado
    const STATE_STORED = 2;      // Almacenado
    const STATE_SHIPPED = 3;     // Enviado (para pedidos terminados)
    const STATE_PROCESSED = 4;   // Procesado (consumido completamente en producción)

    /**
     * Lista de todos los estados válidos
     */
    public static function getValidStates(): array
    {
        return [
            self::STATE_REGISTERED,
            self::STATE_STORED,
            self::STATE_SHIPPED,
            self::STATE_PROCESSED,
        ];
    }

    /**
     * Obtener el nombre del estado como string
     */
    public static function getStateName(int $stateId): string
    {
        return match ($stateId) {
            self::STATE_REGISTERED => 'registered',
            self::STATE_STORED => 'stored',
            self::STATE_SHIPPED => 'shipped',
            self::STATE_PROCESSED => 'processed',
            default => 'unknown',
        };
    }

    /**
     * Obtener el estado como array asociativo (compatible con API existente)
     */
    public function getStateArrayAttribute(): array
    {
        return [
            'id' => $this->state_id,
            'name' => self::getStateName($this->state_id),
        ];
    }

    protected $fillable = ['observations', 'state_id'];


    public function palletBoxes()
    {
        return $this->hasMany(PalletBox::class);
    }

    /**
     * @deprecated Ya no se usa la relación con PalletState
     * Usar $pallet->state_id directamente o $pallet->stateArray
     */
    public function palletState()
    {
        // Retornar un objeto compatible con la API para mantener retrocompatibilidad temporal
        return new class($this->state_id) {
            public $id;
            public $name;

            public function __construct($stateId)
            {
                $this->id = $stateId;
                $this->name = \App\Models\Pallet::getStateName($stateId);
            }

            public function toArrayAssoc()
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                ];
            }
        };
    }

    /* getArticlesAttribute from boxes.boxes.article.article  */
    public function getArticlesAttribute()
    {
        $articles = [];
        $this->boxes->map(function ($box) use (&$articles) {
            $article = $box->box->article->article;
            if (!isset($articles[$article->id])) {
                $articles[$article->id] = $article;
            }
        });
        return $articles;
    }

    /* Article names list array*/
    public function getArticlesNamesAttribute()
    {
        return array_map(function ($article) {
            return $article->name;
        }, $this->articles);
    }



    /* public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    } */

    public function boxes()
    {
        return $this->hasMany(PalletBox::class, 'pallet_id');
    }


    public function boxesV2()
    {
        return $this->belongsToMany(Box::class, 'pallet_boxes', 'pallet_id', 'box_id');
    }


    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    //Resumen de articulos : devuelve un array de articulos, cajas por articulos y cantidad total por articulos
    public function getSummaryAttribute()
    {
        $summary = [];
        $this->boxes->map(function ($box) use (&$summary) {
            $product = $box->box->product;
            if (!isset($summary[$product->id])) {
                $summary[$product->id] = [
                    'product' => $product,
                    'species' => $product->species,
                    'boxes' => 0,
                    'netWeight' => 0,
                ];
            }
            $summary[$product->id]['boxes']++;
            $summary[$product->id]['netWeight'] += $box->box->net_weight;
        });
        return $summary;
    }

    //Accessor
    public function getNetWeightAttribute()
    {
        /* $netWeight = 0;
        $this->boxes->map(function ($box) {
            global $netWeight;
            var_dump($box->net_weight);
            $netWeight += $box->net_weight;
        });
        return $netWeight; */
        //dd($this->boxes);
        return $this->boxes->reduce(function ($carry, $box) {
            return $carry + $box->net_weight;
        }, 0);
    }

    /* numero total de cajas */
    public function getNumberOfBoxesAttribute()
    {
        return $this->boxes->count();
    }

    /**
     * Cantidad de cajas disponibles (no usadas en producción)
     */
    public function getAvailableBoxesCountAttribute()
    {
        return $this->boxes->filter(function ($palletBox) {
            return $palletBox->box->isAvailable;
        })->count();
    }

    /**
     * Cantidad de cajas usadas en producción
     */
    public function getUsedBoxesCountAttribute()
    {
        return $this->boxes->filter(function ($palletBox) {
            return !$palletBox->box->isAvailable;
        })->count();
    }

    /**
     * Peso total neto de las cajas disponibles
     */
    public function getTotalAvailableWeightAttribute()
    {
        return $this->boxes->filter(function ($palletBox) {
            return $palletBox->box->isAvailable;
        })->sum(function ($palletBox) {
            return $palletBox->box->net_weight ?? 0;
        });
    }

    /**
     * Peso total neto de las cajas usadas en producción
     */
    public function getTotalUsedWeightAttribute()
    {
        return $this->boxes->filter(function ($palletBox) {
            return !$palletBox->box->isAvailable;
        })->sum(function ($palletBox) {
            return $palletBox->box->net_weight ?? 0;
        });
    }

    public function getPositionAttribute()
    {
        $pallet = StoredPallet::where('pallet_id', $this->id)->first();

        if ($pallet) {
            return $pallet->position;
        } else {
            return null;
        }
    }

    public function getPositionV2Attribute()
    {

        // Si viene por otra vía, consulta manual
        return $this->storedPallet?->position;
    }



    public function getStoreIdAttribute()
    {
        $pallet = StoredPallet::where('pallet_id', $this->id)->first();
        if ($pallet) {
            return $pallet->store_id;
        } else {

            return null;
        }
    }

    public function getStoreAttribute()
    {
        $pallet = StoredPallet::where('pallet_id', $this->id)->first();
        if ($pallet) {
            return $pallet->store;
        } else {

            return null;
        }
    }

    public function storedPallet()
    {
        return $this->hasOne(StoredPallet::class, 'pallet_id');
    }

    /* Totals, boxes and netweight */
    public function getTotalsAttribute()
    {
        $totals = [
            'boxes' => 0,
            'netWeight' => 0,
        ];
        $this->boxes->map(function ($box) use (&$totals) {
            $totals['boxes']++;
            $totals['netWeight'] += $box->net_weight;
        });
        return $totals;
    }


    public function unStore()
    {
        $pallet = StoredPallet::where('pallet_id', $this->id)->first();
        if ($pallet) {
            $pallet->delete();
        }
    }

    /**
     * Actualiza el estado del palet basado en las cajas disponibles/usadas
     * Se llama automáticamente cuando cambian los ProductionInputs
     */
    public function updateStateBasedOnBoxes(): void
    {
        // Recargar el modelo y sus relaciones
        $this->refresh();
        
        // Cargar cajas con producción inputs si no están cargadas
        if (!$this->relationLoaded('boxes')) {
            $this->load(['boxes.box.productionInputs']);
        } elseif (!$this->boxes->first() || !$this->boxes->first()->relationLoaded('box')) {
            $this->load(['boxes.box.productionInputs']);
        }

        $usedBoxesCount = $this->usedBoxesCount;
        $totalBoxes = $this->numberOfBoxes;

        // Si todas las cajas están usadas → procesado
        if ($usedBoxesCount > 0 && $usedBoxesCount === $totalBoxes) {
            $this->changeToProcessed();
        }
        // Si todas las cajas están disponibles (y antes tenía algunas usadas) → registrado
        elseif ($usedBoxesCount === 0 && $totalBoxes > 0) {
            $this->changeToRegistered();
        }
        // Si está parcialmente consumido, mantener estado actual
    }

    /**
     * Cambiar palet a estado "registrado" y quitar almacenamiento
     */
    public function changeToRegistered(): void
    {
        if ($this->state_id !== self::STATE_REGISTERED) {
            $this->state_id = self::STATE_REGISTERED;
            $this->save();
        }
        // Quitar almacenamiento si existe
        $this->unStore();
    }

    /**
     * Cambiar palet a estado "procesado" y quitar almacenamiento
     */
    public function changeToProcessed(): void
    {
        if ($this->state_id !== self::STATE_PROCESSED) {
            $this->state_id = self::STATE_PROCESSED;
            $this->save();
        }
        // Quitar almacenamiento si existe
        $this->unStore();
    }

    /**
     * Cambiar palet a estado "enviado" y quitar almacenamiento
     * Mantiene el order_id para trazabilidad
     */
    public function changeToShipped(): void
    {
        if ($this->state_id !== self::STATE_SHIPPED) {
            $this->state_id = self::STATE_SHIPPED;
            $this->save();
        }
        // Quitar almacenamiento si existe
        $this->unStore();
        // NO eliminar order_id - mantener vinculación
    }

    public function delete()
    {
        foreach ($this->boxes as $box) {
            $box->delete();
        }

        parent::delete();
    }


    public function getLotsAttribute()
    {
        $lots = [];
        $this->boxes->map(function ($box) use (&$lots) {
            $lot = $box->box->lot;
            /* push lot si no hay igual, almacenar un array de lots sin clave*/
            if (!in_array($lot, $lots)) {
                $lots[] = $lot;
            }
        });

        return $lots;
    }

    /* Nuevo V2 */

    /* Products names list array*/
    public function getProductsNamesAttribute()
    {
        return array_values(array_map(fn($product) => $product->name ?? null, $this->products));
    }


    public function getProductsAttribute()
    {
        $articles = [];
        $this->boxes->map(function ($box) use (&$articles) {
            $article = $box->box->article->article;
            if (!isset($articles[$article->id])) {
                $articles[$article->id] = $article;
            }
        });
        return $articles;
    }

    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'observations' => $this->observations,
            'state' => $this->stateArray,
            'boxes' => $this->boxes->map(function ($box) {
                return $box->toArrayAssoc();
            }),
            'netWeight' => $this->netWeight,
            'productsNames' => $this->productsNames,
            'lots' => $this->lots,
            'numberOfBoxes' => $this->numberOfBoxes,
        ];
    }

    public function toArrayAssocV2()
    {
        return [
            'id' => $this->id,
            'observations' => $this->observations,
            'state' => $this->stateArray,
            'boxes' => $this->boxesV2->map(function ($box) {
                return $box->toArrayAssocV2();
            }),
            'netWeight' => $this->netWeight,
            'productsNames' => $this->productsNames,
            'lots' => $this->lots,
            'numberOfBoxes' => $this->numberOfBoxes,
            'position' => $this->positionV2,
            'orderId' => $this->order_id,
            // Campos calculados para cajas disponibles y usadas
            'availableBoxesCount' => $this->availableBoxesCount,
            'usedBoxesCount' => $this->usedBoxesCount,
            'totalAvailableWeight' => $this->totalAvailableWeight !== null ? round($this->totalAvailableWeight, 3) : null,
            'totalUsedWeight' => $this->totalUsedWeight !== null ? round($this->totalUsedWeight, 3) : null,
        ];
    }


    /* NUEVO LA PESQUERAPP */

    public function scopeStored($query)
    {
        return $query->where('state_id', 2);
    }

    public function scopeJoinBoxes($query)
    {
        return $query
            ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id');
    }

    public function scopeJoinProducts($query)
    {
        return $query
            ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('products', 'products.id', '=', 'boxes.article_id'); // ahora join correcto
    }



}
