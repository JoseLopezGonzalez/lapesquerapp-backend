<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FAOZone extends Model
{
    use UsesTenantConnection;

    protected $table = 'fao_zones';

    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FAOZone::class, 'parent_id');
    }

    public function subzones(): HasMany
    {
        return $this->hasMany(FAOZone::class, 'parent_id');
    }
}
