<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class OrderMaritimeContainer extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'order_id',
        'container_number',
        'seal_number',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class, 'order_maritime_container_id');
    }

    private const KG_TO_LB = 2.20462;

    /**
     * Mercancía del contenedor agrupada por especie y producto, para el Export Packing List.
     * Requiere pallets.boxes.box.product.species(.fishingGear) precargados.
     */
    public function getPackingListSummaryAttribute(): array
    {
        $bySpecies = [];

        foreach ($this->pallets as $pallet) {
            foreach ($pallet->boxes as $palletBox) {
                $box = $palletBox->box ?? null;
                $product = $box?->product;
                $species = $product?->species;

                if (! $box || ! $product || ! $species) {
                    continue;
                }

                $speciesKey = $species->id;
                if (! isset($bySpecies[$speciesKey])) {
                    $bySpecies[$speciesKey] = [
                        'species' => $species,
                        'products' => [],
                        'boxes' => 0,
                        'netWeightKg' => 0.0,
                        'grossWeightKg' => 0.0,
                    ];
                }

                $productKey = $product->id;
                if (! isset($bySpecies[$speciesKey]['products'][$productKey])) {
                    $bySpecies[$speciesKey]['products'][$productKey] = [
                        'product' => $product,
                        'boxes' => 0,
                        'netWeightKg' => 0.0,
                        'grossWeightKg' => 0.0,
                    ];
                }

                $netWeight = (float) ($box->net_weight ?? 0);
                $grossWeight = (float) ($box->gross_weight ?? 0);

                $bySpecies[$speciesKey]['products'][$productKey]['boxes']++;
                $bySpecies[$speciesKey]['products'][$productKey]['netWeightKg'] += $netWeight;
                $bySpecies[$speciesKey]['products'][$productKey]['grossWeightKg'] += $grossWeight;

                $bySpecies[$speciesKey]['boxes']++;
                $bySpecies[$speciesKey]['netWeightKg'] += $netWeight;
                $bySpecies[$speciesKey]['grossWeightKg'] += $grossWeight;
            }
        }

        foreach ($bySpecies as &$speciesGroup) {
            $speciesGroup['netWeightLb'] = $speciesGroup['netWeightKg'] * self::KG_TO_LB;
            $speciesGroup['grossWeightLb'] = $speciesGroup['grossWeightKg'] * self::KG_TO_LB;
            $speciesGroup['products'] = array_values(array_map(function ($productGroup) {
                $productGroup['netWeightLb'] = $productGroup['netWeightKg'] * self::KG_TO_LB;
                $productGroup['grossWeightLb'] = $productGroup['grossWeightKg'] * self::KG_TO_LB;

                return $productGroup;
            }, $speciesGroup['products']));
        }

        return array_values($bySpecies);
    }

    public function getPackingListTotalsAttribute(): array
    {
        $summary = $this->packingListSummary;

        $totals = [
            'boxes' => 0,
            'netWeightKg' => 0.0,
            'grossWeightKg' => 0.0,
        ];

        foreach ($summary as $speciesGroup) {
            $totals['boxes'] += $speciesGroup['boxes'];
            $totals['netWeightKg'] += $speciesGroup['netWeightKg'];
            $totals['grossWeightKg'] += $speciesGroup['grossWeightKg'];
        }

        $totals['netWeightLb'] = $totals['netWeightKg'] * self::KG_TO_LB;
        $totals['grossWeightLb'] = $totals['grossWeightKg'] * self::KG_TO_LB;

        return $totals;
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'containerNumber' => $this->container_number,
            'sealNumber' => $this->seal_number,
            'palletIds' => $this->relationLoaded('pallets') ? $this->pallets->pluck('id')->values() : null,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * Boot del modelo - Validaciones de integridad.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $container) {
            if (empty($container->container_number)) {
                throw ValidationException::withMessages([
                    'container_number' => 'El número de contenedor es obligatorio.',
                ]);
            }
        });
    }
}
