<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuxiliaryProduct extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'name',
        'reference',
        'unit',
        'default_price',
        'notes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'default_price' => 'decimal:4',
    ];

    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderAuxiliaryLine::class, 'auxiliary_product_id');
    }

    public function isInUse(): bool
    {
        return $this->orderLines()->exists();
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'reference' => $this->reference,
            'unit' => $this->unit,
            'defaultPrice' => $this->default_price,
            'notes' => $this->notes,
            'active' => $this->active,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
