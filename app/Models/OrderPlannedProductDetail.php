<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class OrderPlannedProductDetail  extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'tax_id',
        'quantity',
        'boxes',
        'unit_price',
      /*   'line_base',
        'line_total', */
        /* 'pallets', */
        /* 'discount_type', */
        /* 'discount_value', */
    ];

    /**
     * Relación inversa: cada OrderDetail pertenece a un Order.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }

    /* To array Assoc */
    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'product' => $this->product->toArrayAssoc(),
            'tax' => $this->tax->toArrayAssoc(),
            'quantity' => $this->quantity,
            'boxes' => $this->boxes,
            'unitPrice' => $this->unit_price,
           /*  'subTotal' => $this->line_base,
            'total' => $this->line_total, */
            /* 'pallets' => $this->pallets, */
            /* 'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value, */
        ];
    }

    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($detail) {
            // Validar quantity > 0 (si se usa)
            if ($detail->quantity !== null && $detail->quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'La cantidad debe ser mayor que 0.',
                ]);
            }

            // Validar unit_price ≥ 0
            if ($detail->unit_price !== null && $detail->unit_price < 0) {
                throw ValidationException::withMessages([
                    'unit_price' => 'El precio unitario no puede ser negativo.',
                ]);
            }
        });
    }

}
