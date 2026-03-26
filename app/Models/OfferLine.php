<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferLine extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    public $timestamps = false;

    protected $fillable = [
        'offer_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'tax_id',
        'boxes',
        'currency',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'boxes' => 'integer',
        'created_at' => 'datetime',
    ];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'offerId' => $this->offer_id,
            'product' => $this->relationLoaded('product') ? $this->product?->toArrayAssoc() : null,
            'productId' => $this->product_id,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unitPrice' => (float) $this->unit_price,
            'tax' => $this->relationLoaded('tax') ? $this->tax?->toArrayAssoc() : null,
            'taxId' => $this->tax_id,
            'boxes' => $this->boxes,
            'currency' => $this->currency,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
