<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    /**
     * Estados válidos del pedido
     */
    const STATUS_PENDING = 'pending';
    const STATUS_FINISHED = 'finished';
    const STATUS_INCIDENT = 'incident';

    /**
     * Lista de todos los estados válidos
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_FINISHED,
            self::STATUS_INCIDENT,
        ];
    }

    protected $fillable = [
        'customer_id',
        'payment_term_id',
        'billing_address',
        'shipping_address',
        'transportation_notes',
        'production_notes',
        'accounting_notes',
        'salesperson_id',
        'emails',
        'transport_id',
        'entry_date',
        'load_date',
        'status',
        'buyer_reference',
        'incoterm_id'
    ];

    public function plannedProductDetails()
    {
        return $this->hasMany(OrderPlannedProductDetail::class);
    }

    /* Id formateado #00_ _ _ , rellenar con 0 a la izquierda si no tiene 5 digitos y añadir un # al principio */
    public function getFormattedIdAttribute()
    {
        return '#' . str_pad($this->id, 5, '0', STR_PAD_LEFT);
    }

    // Relación con Incoterm
    public function incoterm()
    {
        return $this->belongsTo(Incoterm::class, 'incoterm_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesperson()
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function transport()
    {
        return $this->belongsTo(Transport::class);
    }

    public function pallets()
    {
        return $this->hasMany(Pallet::class);
    }

    //Resumen productos pedido

    /**
     * Obtener resumen de productos del pedido
     * Retorna un array con información resumida de los productos planificados
     */
    public function getSummaryAttribute()
    {
        $plannedProducts = $this->plannedProductDetails;

        if (!$plannedProducts || $plannedProducts->isEmpty()) {
            return [
                'totalProducts' => 0,
                'totalQuantity' => 0,
                'totalBoxes' => 0,
                'totalAmount' => 0,
            ];
        }

        return [
            'totalProducts' => $plannedProducts->count(),
            'totalQuantity' => $plannedProducts->sum('quantity'),
            'totalBoxes' => $plannedProducts->sum('boxes'),
            'totalAmount' => $plannedProducts->sum(function ($detail) {
                return ($detail->unit_price ?? 0) * ($detail->quantity ?? 0);
            }),
        ];
    }

    public function payment_term()
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function isActive()
    {
        return $this->status == 'pending' || $this->load_date >= now();
    }

    // Resumen de productos agrupados por especie && zona de captura, necesito 

    public function getProductsBySpeciesAndCaptureZoneAttribute()
    {
        $summary = [];
        $this->pallets->map(function ($pallet) use (&$summary) {
            $pallet->boxes->map(function ($box) use (&$summary) {
                // Solo incluir cajas disponibles (no usadas en producción)
                if (!$box->box->isAvailable) {
                    return;
                }

                $product = $box->box->product;
                $species = $product->species;
                $captureZone = $product->captureZone;
                $key = $species->id . '-' . $captureZone->id;

                if (!isset($summary[$key])) {
                    $summary[$key] = [
                        'species' => $species,
                        'captureZone' => $captureZone,
                        'products' => []
                    ];
                }

                $productKey = $product->id;
                if (!isset($summary[$key]['products'][$productKey])) {
                    $summary[$key]['products'][$productKey] = [
                        'product' => $product,
                        'boxes' => 0,
                        'netWeight' => 0
                    ];
                }

                $summary[$key]['products'][$productKey]['boxes']++;
                $summary[$key]['products'][$productKey]['netWeight'] += $box->box->net_weight;
            });
        });

        return $summary;
    }


    public function getTotalsAttribute()
    {
        $totals = [
            'boxes' => 0,
            'netWeight' => 0
        ];

        if ($this->pallets) {
            $this->pallets->map(function ($pallet) use (&$totals) {
                if ($pallet && $pallet->boxes) {
                    $pallet->boxes->map(function ($box) use (&$totals) {
                        // Solo incluir cajas disponibles (no usadas en producción)
                        if ($box && $box->box && $box->box->isAvailable) {
                            $totals['boxes']++;
                            $totals['netWeight'] += $box->box->net_weight ?? 0;
                        }
                    });
                }
            });
        }

        return $totals;
    }



    public function getNumberOfPalletsAttribute()
    {
        return $this->pallets ? $this->pallets->count() : 0;
    }

    public function getLotsAttribute()
    {
        $lots = [];
        // Asumiendo que $this->pallets es una colección
        if ($this->pallets) {
            $this->pallets->each(function ($pallet) use (&$lots) {
                if ($pallet && is_array($pallet->lots)) {
                    // Asegúrate de que $pallet->lots sea un array antes de intentar iterar sobre él
                    foreach ($pallet->lots as $lot) {
                        if (!in_array($lot, $lots)) {
                            $lots[] = $lot;
                        }
                    }
                }
            });
        }

        return $lots; // Devuelve la lista acumulada de lotes únicos
    }

    /* some pallets on storage status */
    public function hasPalletsOnStorage()
    {
        if (!$this->pallets) {
            return false;
        }
        return $this->pallets->some(function ($pallet) {
            return $pallet && $pallet->status == \App\Models\Pallet::STATE_STORED;
        });
    }

    /**
     * Get the array of regular emails.
     *
     * @return array
     */
    public function getEmailsArrayAttribute()
    {
        return $this->extractEmails('regular');
    }


    /**
     * Get the array of CC emails.
     *
     * @return array
     */
    public function getCcEmailsArrayAttribute()
    {
        return $this->extractEmails('cc');
    }



    /**
     * Helper method to extract emails based on type.
     *
     * @param string $type 'regular' or 'cc'
     * @return array
     */
    protected function extractEmails($type)
    {
        $emails = explode(';', $this->emails);
        $result = [];

        foreach ($emails as $email) {
            $email = trim($email);
            if (empty($email)) {
                continue;
            }

            if ($type == 'cc' && (str_starts_with($email, 'CC:') || str_starts_with($email, 'cc:'))) {
                $result[] = substr($email, 3);  // Remove 'CC:' prefix and add to results 
            } elseif ($type == 'regular' && !str_starts_with($email, 'CC:') && !str_starts_with($email, 'cc:')) {
                $result[] = $email;  // Add regular email to results
            }
        }

        return $result;
    }


    /* Nuevo V2 */

    public function getTotalNetWeightAttribute()
    {
        try {
            return $this->pallets->sum(function ($pallet) {
                if (!$pallet->relationLoaded('boxes')) {
                    $pallet->load('boxes.box.productionInputs');
                }
                
                return $pallet->boxes->sum(function ($palletBox) {
                    // Solo incluir cajas disponibles (no usadas en producción)
                    if (!$palletBox->box) {
                        return 0;
                    }
                    
                    // Asegurar que productionInputs esté cargado
                    if (!$palletBox->box->relationLoaded('productionInputs')) {
                        $palletBox->box->load('productionInputs');
                    }
                    
                    try {
                        return $palletBox->box->isAvailable ? ($palletBox->box->net_weight ?? 0) : 0;
                    } catch (\Exception $e) {
                        \Log::warning('Error checking isAvailable in getTotalNetWeightAttribute: ' . $e->getMessage());
                        return 0;
                    }
                });
            });
        } catch (\Exception $e) {
            \Log::error('Error in getTotalNetWeightAttribute: ' . $e->getMessage());
            return 0;
        }
    }

    public function getTotalBoxesAttribute()
    {
        return $this->pallets->sum(function ($pallet) {
            return $pallet->boxes->filter(function ($palletBox) {
                // Solo contar cajas disponibles (no usadas en producción)
                return $palletBox->box->isAvailable;
            })->count();
        });
    }


    public function getProductsWithLotsDetailsAttribute()
    {
        $summary = [];

        $this->pallets->map(function ($pallet) use (&$summary) {
            $pallet->boxes->map(function ($box) use (&$summary) {
                // Solo incluir cajas disponibles (no usadas en producción)
                if (!$box->box->isAvailable) {
                    return;
                }

                $product = $box->box->product;
                $lot = $box->box->lot; // Lote de la caja
                $netWeight = $box->box->net_weight; // Peso neto de la caja

                $productKey = $product->id;

                if (!isset($summary[$productKey])) {
                    $summary[$productKey] = [
                        'product' => [
                            'article' => [
                                'id' => $product->article->id,
                                'name' => $product->article->name,
                            ],
                            'boxGtin' => $product->box_gtin,
                            'boxes' => 0,
                            'netWeight' => 0,
                            'species' => [
                                'name' => $product->species->name,
                                'scientificName' => $product->species->scientific_name,
                                'fao' => $product->species->fao,
                            ],
                            'captureZone' => $product->captureZone->name,
                            'fishingGear' => $product->species->fishingGear->name,
                        ],
                        'lots' => []
                    ];
                }

                // Agrupar lotes únicos y sumar pesos y cajas
                $lotIndex = array_search($lot, array_column($summary[$productKey]['lots'], 'lot'));

                if ($lotIndex === false) {
                    // Si el lote no existe, lo añadimos
                    $summary[$productKey]['lots'][] = [
                        'lot' => $lot,
                        'boxes' => 1,
                        'netWeight' => $netWeight,
                    ];
                } else {
                    // Si ya existe, sumamos los valores
                    $summary[$productKey]['lots'][$lotIndex]['boxes']++;
                    $summary[$productKey]['lots'][$lotIndex]['netWeight'] += $netWeight;
                }

                // Sumar totales al producto
                $summary[$productKey]['product']['boxes']++;
                $summary[$productKey]['product']['netWeight'] += $netWeight;
            });
        });

        return array_values($summary);
    }

    /* obtener un listado de productos con cantidades y numero de cajas de todos los palets vinculados */
    public function getProductionProductDetailsAttribute()
    {
        $details = [];
        $this->pallets->map(function ($pallet) use (&$details) {
            $pallet->boxes->map(function ($box) use (&$details) {
                // Solo incluir cajas disponibles (no usadas en producción)
                if (!$box->box->isAvailable) {
                    return;
                }

                $product = $box->box->product;
                $productKey = $product->id;
                if (!isset($details[$productKey])) {
                    $details[$productKey] = [
                        'product' => [
                            'id' => $product->id,
                            'name' => $product->name,
                            'a3erpCode' => $product->a3erp_code,
                            'facilcomCode' => $product->facil_com_code,
                            'species_id' => $product->species_id, // ✅ AÑADIDO AQUÍ
                        ],
                        'boxes' => 0,
                        'netWeight' => 0,
                    ];
                }

                $details[$productKey]['boxes']++;
                $details[$productKey]['netWeight'] += $box->box->net_weight;
            });
        });

        // Redondeamos netWeight a 3 decimales
        foreach ($details as &$detail) {
            $detail['netWeight'] = round($detail['netWeight'], 3);
        }

        return array_values($details);
    }



    /* Confrontar en un mismo array productionProductDetails añadiendo el precio y tax sacado de plannedProductDetail y calculando
    el subtotal (base sin tax) y total (base + tax) */

    public function getProductDetailsAttribute()
    {
        $productionProductDetails = $this->productionProductDetails;
        $plannedProductDetails = $this->plannedProductDetails;

        $details = [];

        foreach ($productionProductDetails as $productionProductDetail) {
            $product = $productionProductDetail['product'];
            $productKey = $product['id'];

            // Añadimos species_id si existe la relación
            $speciesId = $product['species_id'] ?? null;

            $details[$productKey]['product'] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'a3erpCode' => $product['a3erpCode'],
                'facilcomCode' => $product['facilcomCode'],
                'species_id' => $speciesId,
            ];

            $details[$productKey]['boxes'] = $productionProductDetail['boxes'];
            $details[$productKey]['netWeight'] = $productionProductDetail['netWeight'];

            $plannedProductDetail = $plannedProductDetails->firstWhere('product_id', $product['id']);
            if ($plannedProductDetail) {
                $details[$productKey]['unitPrice'] = $plannedProductDetail->unit_price;
                $details[$productKey]['tax'] = $plannedProductDetail->tax;
                $details[$productKey]['subtotal'] = $details[$productKey]['unitPrice'] * $details[$productKey]['netWeight'];
                $details[$productKey]['total'] = $details[$productKey]['subtotal'] + ($details[$productKey]['subtotal'] * $details[$productKey]['tax']->rate / 100);
            } else {
                $details[$productKey]['unitPrice'] = 0;
                $details[$productKey]['tax'] = ['rate' => 0];
                $details[$productKey]['subtotal'] = 0;
                $details[$productKey]['total'] = 0;
            }
        }

        return array_values($details);
    }


    /* Subtotal Atribute */
    public function getSubtotalAmountAttribute()
    {
        return collect($this->productDetails)->sum('subtotal');
    }

    /* Total Atribute */
    public function getTotalAmountAttribute()
    {
        return collect($this->productDetails)->sum('total');
    }

    /* incident only one */
    public function incident()
    {
        return $this->hasOne(Incident::class);
    }

    /**
     * Marcar el pedido como incidente
     * Se usa cuando se crea un incidente asociado al pedido
     * 
     * @return bool
     */
    public function markAsIncident(): bool
    {
        return $this->update(['status' => self::STATUS_INCIDENT]);
    }

    /**
     * Finalizar el pedido después de resolver un incidente
     * Cambia el estado a 'finished' y marca todos los palets como 'shipped'
     * 
     * @return bool
     */
    public function finalizeAfterIncident(): bool
    {
        $this->status = self::STATUS_FINISHED;
        $saved = $this->save();

        if ($saved) {
            $this->load('pallets');
            foreach ($this->pallets as $pallet) {
                $pallet->changeToShipped();
            }
        }

        return $saved;
    }


    public function getSpeciesListAttribute()
    {
        $species = collect();

        $this->pallets->each(function ($pallet) use (&$species) {
            $pallet->boxes->each(function ($box) use (&$species) {
                // Solo incluir cajas disponibles (no usadas en producción)
                if (!$box->box->isAvailable) {
                    return;
                }

                $product = $box->box->product;
                if ($product && $product->species) {
                    $species->put($product->species->id, [
                        'id' => $product->species->id,
                        'name' => $product->species->name,
                        'scientificName' => $product->species->scientific_name,
                        'fao' => $product->species->fao,
                    ]);
                }
            });
        });

        return $species->values();
    }

    public function getFamiliesListAttribute()
    {
        $families = collect();

        $this->pallets->each(function ($pallet) use (&$families) {
            $pallet->boxes->each(function ($box) use (&$families) {
                // Solo incluir cajas disponibles (no usadas en producción)
                if (!$box->box->isAvailable) {
                    return;
                }

                $product = $box->box->product;
                if ($product && $product->family) {
                    $families->put($product->family->id, [
                        'id' => $product->family->id,
                        'name' => $product->family->name,
                    ]);
                }
            });
        });

        return $families->values();
    }

    public function getCategoriesListAttribute()
    {
        $categories = collect();

        $this->pallets->each(function ($pallet) use (&$categories) {
            $pallet->boxes->each(function ($box) use (&$categories) {
                // Solo incluir cajas disponibles (no usadas en producción)
                if (!$box->box->isAvailable) {
                    return;
                }

                $product = $box->box->product;
                if ($product && $product->family && $product->family->category) {
                    $categories->put($product->family->category->id, [
                        'id' => $product->family->category->id,
                        'name' => $product->family->category->name,
                    ]);
                }
            });
        });

        return $categories->values();
    }

    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($order) {
            // Validar entry_date ≤ load_date
            if ($order->entry_date && $order->load_date) {
                if ($order->entry_date > $order->load_date) {
                    throw ValidationException::withMessages([
                        'load_date' => 'La fecha de carga debe ser mayor o igual a la fecha de entrada.',
                    ]);
                }
            }

            // Validar status valores válidos
            if ($order->status && !in_array($order->status, self::getValidStatuses())) {
                throw ValidationException::withMessages([
                    'status' => 'El estado del pedido no es válido. Valores permitidos: ' . implode(', ', self::getValidStatuses()),
                ]);
            }
        });
    }



    /* NUEVO ACTUALIZADO 2025 LA PESQUERAPP--------------------------------------- */

    public function scopeBetweenLoadDates($query, $from, $to)
    {
        return $query->whereBetween('load_date', [$from, $to]);
    }

    // En Order.php

    public function scopeWhereBoxArticleSpecies($query, $speciesId)
    {
        if ($speciesId) {
            $query->where('articles.species_id', $speciesId);
        }

        return $query;
    }


    public function scopeJoinBoxesAndArticles($query)
    {
        return $query
            ->join('pallets', 'pallets.order_id', '=', 'orders.id')
            ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
            ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('articles', 'articles.id', '=', 'boxes.article_id');
    }

    public function scopeWithPlannedProductDetails($query)
    {
        return $query->with('plannedProductDetails.product');
    }

    public function scopeWherePlannedProductSpecies($query, ?int $speciesId)
    {
        if ($speciesId) {
            $query->whereHas('plannedProductDetails.product', function ($q) use ($speciesId) {
                $q->where('species_id', $speciesId);
            });
        }

        return $query;
    }



    // En Order.php
    public static function executeNetWeightSum($query): float
    {
        return (float) $query->sum('boxes.net_weight');
    }

    public function scopeWithCustomerCountry($query)
    {
        return $query->with(['customer.country']);
    }

    public function scopeWithPlannedProductDetailsAndSpecies($query)
    {
        return $query->with(['plannedProductDetails.product.species']);
    }

    /**
     * Scope para cargar relaciones necesarias para calcular totalNetWeight y totalBoxes
     * Evita queries N+1 cuando se accede a estos attributes en colecciones
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithTotals($query)
    {
        return $query->with([
            'pallets.boxes.box.productionInputs', // Para determinar disponibilidad de cajas
            'pallets.boxes.box.product', // Para cálculos si es necesario
        ]);
    }














}
