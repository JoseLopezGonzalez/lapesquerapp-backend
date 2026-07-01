<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class OrderAuxiliaryLine extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'order_id',
        'auxiliary_product_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'tax_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function auxiliaryProduct(): BelongsTo
    {
        return $this->belongsTo(AuxiliaryProduct::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /**
     * Nombre efectivo: catálogo > descripción libre.
     */
    public function getEffectiveDescriptionAttribute(): string
    {
        return $this->auxiliaryProduct?->name ?? $this->description ?? '';
    }

    /**
     * Subtotal sin IVA.
     */
    public function getSubtotalAttribute(): float
    {
        return round((float) $this->unit_price * (float) $this->quantity, 4);
    }

    /**
     * Total con IVA.
     */
    public function getTotalAttribute(): float
    {
        $rate = (float) ($this->tax?->rate ?? 0);

        return round($this->subtotal * (1 + $rate / 100), 4);
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'auxiliaryProduct' => $this->relationLoaded('auxiliaryProduct') ? $this->auxiliaryProduct?->toArrayAssoc() : null,
            'description' => $this->description,
            'effectiveDescription' => $this->effective_description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unitPrice' => $this->unit_price,
            'tax' => $this->relationLoaded('tax') ? $this->tax?->toArrayAssoc() : null,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
        ];
    }

    /**
     * Boot del modelo - Validaciones de integridad de línea.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $line) {
            if (empty($line->auxiliary_product_id) && empty($line->description)) {
                throw ValidationException::withMessages([
                    'description' => 'Se requiere un artículo del catálogo o una descripción libre.',
                ]);
            }

            if ($line->quantity !== null && $line->quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'La cantidad debe ser mayor que cero.',
                ]);
            }

            if ($line->unit_price !== null && $line->unit_price < 0) {
                throw ValidationException::withMessages([
                    'unit_price' => 'El precio unitario no puede ser negativo.',
                ]);
            }
        });
    }
}
