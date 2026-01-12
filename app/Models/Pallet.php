<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;


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
            'id' => $this->status,
            'name' => self::getStateName($this->status),
        ];
    }

    protected $fillable = ['observations', 'status', 'reception_id'];

    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Validar antes de guardar
        static::saving(function ($pallet) {
            $pallet->validatePalletRules();
        });

        // Validar antes de actualizar
        static::updating(function ($pallet) {
            $pallet->validateUpdateRules();
            
            // No permitir cambiar la recepción de un palet
            if ($pallet->reception_id !== null && $pallet->isDirty('reception_id')) {
                throw new \Exception('No se puede cambiar la recepción de un palet.');
            }
        });
        
        // Validar antes de eliminar
        static::deleting(function ($pallet) {
            // No permitir eliminar un palet que proviene de una recepción directamente
            if ($pallet->reception_id !== null) {
                throw new \Exception('No se puede eliminar un palet que proviene de una recepción. Elimine la recepción o modifique desde la recepción.');
            }
        });
    }

    /**
     * Validar reglas de Pallet al guardar
     */
    protected function validatePalletRules(): void
    {
        // Validar que status sea válido
        if (!in_array($this->status, self::getValidStates())) {
            throw new \InvalidArgumentException(
                "El estado (status) debe ser uno de: " . implode(', ', self::getValidStates()) . ". Valor recibido: {$this->status}."
            );
        }
    }

    /**
     * Validar reglas al actualizar Pallet
     */
    protected function validateUpdateRules(): void
    {
        $originalState = $this->getOriginal('status');
        $newState = $this->status;
        
        // Permitir cambiar de PROCESSED a REGISTERED cuando se liberan cajas de producción
        // Esto se hace automáticamente cuando se eliminan ProductionInputs
        if ($originalState === self::STATE_PROCESSED && $this->isDirty('status')) {
            // Solo permitir cambiar a REGISTERED (cuando se liberan cajas)
            if ($newState !== self::STATE_PROCESSED && $newState !== self::STATE_REGISTERED) {
                throw new \InvalidArgumentException(
                    'Un palet procesado solo puede cambiar a registrado cuando se liberan cajas de producción. No se puede cambiar a otros estados.'
                );
            }
        }

        // No permitir cambiar de status = 3 (enviado) a status = 1 o 2
        // EXCEPCIÓN: Permitir cambiar a REGISTERED cuando se desvincula de un pedido (order_id está cambiando a null)
        // Esto permite que cuando se desvincula un palet, pueda volver a estado registrado
        if ($originalState === self::STATE_SHIPPED && $this->isDirty('status')) {
            $isUnlinking = $this->isDirty('order_id') && $this->order_id === null;
            if (in_array($newState, [self::STATE_REGISTERED, self::STATE_STORED]) && !$isUnlinking) {
                throw new \InvalidArgumentException(
                    'No se puede cambiar un palet enviado de vuelta a registrado o almacenado. Un palet enviado no puede volver a almacén.'
                );
            }
        }
    }


    public function palletBoxes()
    {
        return $this->hasMany(PalletBox::class);
    }

    /**
     * @deprecated Ya no se usa la relación con PalletState
     * Usar $pallet->status directamente o $pallet->stateArray
     */
    public function palletState()
    {
        // Retornar un objeto compatible con la API para mantener retrocompatibilidad temporal
        return new class($this->status) {
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

    /**
     * @deprecated Ya no se usa la relación con PalletState
     * Usar $pallet->status directamente o $pallet->stateArray
     * 
     * NOTA: Este método NO es una relación Eloquent, es un método helper
     * que retorna un objeto fake para mantener compatibilidad con la API
     */
    public function state()
    {
        // Retornar un objeto compatible con la API para mantener retrocompatibilidad temporal
        return new class($this->status) {
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
        if ($this->boxes) {
            $this->boxes->map(function ($box) use (&$articles) {
                if ($box && $box->box && $box->box->article && $box->box->article->article) {
                    $article = $box->box->article->article;
                    if (!isset($articles[$article->id])) {
                        $articles[$article->id] = $article;
                    }
                }
            });
        }
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

    /**
     * Relación con recepción de materia prima
     */
    public function reception()
    {
        return $this->belongsTo(RawMaterialReception::class, 'reception_id');
    }

    /**
     * Determina si el palet proviene de una recepción
     */
    public function getIsFromReceptionAttribute(): bool
    {
        return $this->reception_id !== null;
    }

    //Resumen de articulos : devuelve un array de articulos, cajas por articulos y cantidad total por articulos
    public function getSummaryAttribute()
    {
        $summary = [];
        if ($this->boxes) {
            $this->boxes->map(function ($box) use (&$summary) {
                if ($box && $box->box && $box->box->product) {
                    $product = $box->box->product;
                    if (!isset($summary[$product->id])) {
                        $summary[$product->id] = [
                            'product' => $product,
                            'species' => $product->species ?? null,
                            'boxes' => 0,
                            'netWeight' => 0,
                        ];
                    }
                    $summary[$product->id]['boxes']++;
                    $summary[$product->id]['netWeight'] += $box->box->net_weight ?? 0;
                }
            });
        }
        return $summary;
    }

    //Accessor
    public function getNetWeightAttribute()
    {
        if (!$this->boxes) {
            return 0;
        }
        return $this->boxes->reduce(function ($carry, $box) {
            return $carry + ($box->net_weight ?? 0);
        }, 0);
    }

    /* numero total de cajas */
    public function getNumberOfBoxesAttribute()
    {
        return $this->boxes ? $this->boxes->count() : 0;
    }

    /**
     * Cantidad de cajas disponibles (no usadas en producción)
     */
    public function getAvailableBoxesCountAttribute()
    {
        if (!$this->boxes) {
            return 0;
        }
        return $this->boxes->filter(function ($palletBox) {
            return $palletBox && $palletBox->box && $palletBox->box->isAvailable;
        })->count();
    }

    /**
     * Cantidad de cajas usadas en producción
     */
    public function getUsedBoxesCountAttribute()
    {
        if (!$this->boxes) {
            return 0;
        }
        return $this->boxes->filter(function ($palletBox) {
            return $palletBox && $palletBox->box && !$palletBox->box->isAvailable;
        })->count();
    }

    /**
     * Peso total neto de las cajas disponibles
     */
    public function getTotalAvailableWeightAttribute()
    {
        if (!$this->boxes) {
            return 0;
        }
        return $this->boxes->filter(function ($palletBox) {
            return $palletBox && $palletBox->box && $palletBox->box->isAvailable;
        })->sum(function ($palletBox) {
            return $palletBox->box->net_weight ?? 0;
        });
    }

    /**
     * Peso total neto de las cajas usadas en producción
     */
    public function getTotalUsedWeightAttribute()
    {
        if (!$this->boxes) {
            return 0;
        }
        return $this->boxes->filter(function ($palletBox) {
            return $palletBox && $palletBox->box && !$palletBox->box->isAvailable;
        })->sum(function ($palletBox) {
            return $palletBox->box->net_weight ?? 0;
        });
    }

    /**
     * Calcula el coste por kg del palet (media ponderada de las cajas)
     */
    public function getCostPerKgAttribute(): ?float
    {
        if (!$this->boxes || $this->boxes->isEmpty()) {
            return null;
        }
  
        $totalCost = 0;
        $totalWeight = 0;
  
        foreach ($this->boxes as $palletBox) {
            $box = $palletBox->box;
            $boxCost = $box->total_cost;
            $boxWeight = $box->net_weight;
      
            if ($boxCost !== null && $boxWeight > 0) {
                $totalCost += $boxCost;
                $totalWeight += $boxWeight;
            }
        }
  
        if ($totalWeight == 0) {
            return null;
        }
  
        return $totalCost / $totalWeight;
    }

    /**
     * Calcula el coste total del palet (suma de costes de cajas)
     */
    public function getTotalCostAttribute(): ?float
    {
        if (!$this->boxes || $this->boxes->isEmpty()) {
            return null;
        }
  
        $totalCost = 0;
        $hasCost = false;
  
        foreach ($this->boxes as $palletBox) {
            $boxCost = $palletBox->box->total_cost;
            if ($boxCost !== null) {
                $totalCost += $boxCost;
                $hasCost = true;
            }
        }
  
        return $hasCost ? $totalCost : null;
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
        if ($this->boxes) {
            $this->boxes->map(function ($box) use (&$totals) {
                $totals['boxes']++;
                $totals['netWeight'] += $box->net_weight ?? 0;
            });
        }
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
        // Recargar el modelo desde la base de datos para obtener datos frescos
        $this->refresh();
        
        // Siempre recargar las relaciones necesarias después del refresh
        // para asegurar que tenemos los datos más recientes de productionInputs
        $this->load(['boxes.box.productionInputs']);

        // Calcular conteos directamente desde los datos cargados
        // para evitar problemas con accessors que pueden hacer consultas adicionales
        $totalBoxes = 0;
        $usedBoxesCount = 0;
        
        if ($this->boxes) {
            foreach ($this->boxes as $palletBox) {
                if (!$palletBox || !$palletBox->box) {
                    continue;
                }
                
                $totalBoxes++;
                
                // Verificar directamente si la caja tiene productionInputs
                // usando los datos cargados en lugar del accessor isAvailable
                $box = $palletBox->box;
                $hasProductionInputs = false;
                
                if ($box->relationLoaded('productionInputs')) {
                    // Si la relación está cargada, usar isEmpty()
                    $hasProductionInputs = !$box->productionInputs->isEmpty();
                } else {
                    // Si no está cargada (no debería pasar, pero por seguridad)
                    $hasProductionInputs = $box->productionInputs()->exists();
                }
                
                if ($hasProductionInputs) {
                    $usedBoxesCount++;
                }
            }
        }

        // Si todas las cajas están usadas → procesado
        if ($usedBoxesCount > 0 && $usedBoxesCount === $totalBoxes) {
            $this->changeToProcessed();
        }
        // Si todas las cajas están disponibles → registrado
        elseif ($usedBoxesCount === 0 && $totalBoxes > 0) {
            $this->changeToRegistered();
        }
        // Si está parcialmente consumido
        else {
            // Si el palet estaba completamente procesado (PROCESSED) y ahora solo algunas cajas están usadas,
            // debe volver a REGISTERED porque ya no todas las cajas están consumidas
            if ($this->status === self::STATE_PROCESSED) {
                $this->changeToRegistered();
            }
            // Si está parcialmente consumido pero no estaba en PROCESSED, mantener estado actual
        }
    }

    /**
     * Cambiar palet a estado "registrado" y quitar almacenamiento
     */
    public function changeToRegistered(): void
    {
        if ($this->status !== self::STATE_REGISTERED) {
            $this->status = self::STATE_REGISTERED;
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
        if ($this->status !== self::STATE_PROCESSED) {
            $this->status = self::STATE_PROCESSED;
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
        if ($this->status !== self::STATE_SHIPPED) {
            $this->status = self::STATE_SHIPPED;
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
        if ($this->boxes) {
            $this->boxes->map(function ($box) use (&$lots) {
                if ($box && $box->box) {
                    $lot = $box->box->lot;
                    /* push lot si no hay igual, almacenar un array de lots sin clave*/
                    if (!in_array($lot, $lots)) {
                        $lots[] = $lot;
                    }
                }
            });
        }
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
        if ($this->boxes) {
            $this->boxes->map(function ($box) use (&$articles) {
                if ($box && $box->box && $box->box->article && $box->box->article->article) {
                    $article = $box->box->article->article;
                    if (!isset($articles[$article->id])) {
                        $articles[$article->id] = $article;
                    }
                }
            });
        }
        return $articles;
    }

    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'observations' => $this->observations,
            'state' => $this->stateArray,
            'boxes' => $this->boxes ? $this->boxes->map(function ($box) {
                return $box->toArrayAssoc();
            }) : [],
            'netWeight' => $this->netWeight,
            'productsNames' => $this->productsNames,
            'lots' => $this->lots,
            'numberOfBoxes' => $this->numberOfBoxes,
        ];
    }

    public function toArrayAssocV2()
    {
        try {
            Log::info('🟠 [PALLET MODEL] toArrayAssocV2 iniciado', [
                'pallet_id' => $this->id ?? 'N/A'
            ]);

            $id = $this->id;
            $observations = $this->observations;
            
            Log::info('🟠 [PALLET MODEL] toArrayAssocV2: Obteniendo stateArray');
            $state = $this->stateArray;
            Log::info('🟠 [PALLET MODEL] toArrayAssocV2: stateArray obtenido');

            Log::info('🟠 [PALLET MODEL] toArrayAssocV2: Procesando boxesV2', [
                'boxesV2_exists' => isset($this->boxesV2),
                'boxesV2_count' => $this->boxesV2 ? $this->boxesV2->count() : 0
            ]);

            $boxes = [];
            if ($this->boxesV2) {
                $boxes = $this->boxesV2->map(function ($box) {
                    try {
                        if ($box) {
                            return $box->toArrayAssocV2();
                        }
                        return null;
                    } catch (\Exception $e) {
                        Log::error('🔴 [PALLET MODEL] Error procesando box en toArrayAssocV2', [
                            'box_id' => $box->id ?? 'N/A',
                            'message' => $e->getMessage()
                        ]);
                        return null;
                    }
                })->filter();
            }

            Log::info('🟠 [PALLET MODEL] toArrayAssocV2: Obteniendo accessors');
            $netWeight = $this->netWeight;
            $productsNames = $this->productsNames;
            $lots = $this->lots;
            $numberOfBoxes = $this->numberOfBoxes;
            $position = $this->positionV2;
            $availableBoxesCount = $this->availableBoxesCount;
            $usedBoxesCount = $this->usedBoxesCount;
            $totalAvailableWeight = $this->totalAvailableWeight !== null ? round($this->totalAvailableWeight, 3) : null;
            $totalUsedWeight = $this->totalUsedWeight !== null ? round($this->totalUsedWeight, 3) : null;

            $result = [
                'id' => $id,
                'observations' => $observations,
                'state' => $state,
                'boxes' => $boxes,
                'netWeight' => $netWeight,
                'productsNames' => $productsNames,
                'lots' => $lots,
                'numberOfBoxes' => $numberOfBoxes,
                'position' => $position,
                'orderId' => $this->order_id,
                'availableBoxesCount' => $availableBoxesCount,
                'usedBoxesCount' => $usedBoxesCount,
                'totalAvailableWeight' => $totalAvailableWeight,
                'totalUsedWeight' => $totalUsedWeight,
                'receptionId' => $this->reception_id,
                'costPerKg' => $this->cost_per_kg !== null ? round($this->cost_per_kg, 4) : null,
                'totalCost' => $this->total_cost !== null ? round($this->total_cost, 2) : null,
            ];

            Log::info('🟠 [PALLET MODEL] toArrayAssocV2 completado exitosamente', ['pallet_id' => $this->id ?? 'N/A']);
            return $result;
        } catch (\Exception $e) {
            Log::error('🔴 [PALLET MODEL] Error en toArrayAssocV2', [
                'pallet_id' => $this->id ?? 'N/A',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }


    /* NUEVO LA PESQUERAPP */

    public function scopeStored($query)
    {
        return $query->where('status', self::STATE_STORED);
    }

    /**
     * Scope para palets en stock (registrados o almacenados)
     * Incluye palets con status = 1 (registered) o status = 2 (stored)
     */
    public function scopeInStock($query)
    {
        return $query->whereIn('status', [self::STATE_REGISTERED, self::STATE_STORED]);
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
