<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProspectCategory extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'name',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function prospects()
    {
        return $this->hasMany(Prospect::class, 'category_id');
    }

    public function canBeDeleted(): bool
    {
        return $this->prospects()->count() === 0;
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
        ];
    }
}
