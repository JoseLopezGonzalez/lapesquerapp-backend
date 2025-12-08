<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductFamily extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'family_id');
    }

    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category ? $this->category->toArrayAssoc() : null,
            'active' => $this->active,
        ];
    }
}
