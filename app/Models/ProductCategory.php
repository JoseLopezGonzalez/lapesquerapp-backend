<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function families()
    {
        return $this->hasMany(ProductFamily::class, 'category_id');
    }

    public function products()
    {
        return $this->hasManyThrough(Product::class, ProductFamily::class, 'category_id', 'family_id');
    }

    /**
     * Indica si la categoría puede eliminarse (no tiene familias asociadas).
     */
    public function canBeDeleted(): bool
    {
        return $this->families()->count() === 0;
    }

    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
        ];
    }
}
