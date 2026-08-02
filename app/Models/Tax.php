<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'name',
        'rate',
    ];

    /* To array Assoc */
    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rate' => $this->rate,
        ];
    }

    /**
     * Relación inversa: cada Tax pertenece a muchos OrderDetail.
     */
    public function orderPlannedProductDetails()
    {
        return $this->hasMany(OrderPlannedProductDetail::class);
    }
}
