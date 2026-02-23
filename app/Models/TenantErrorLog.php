<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantErrorLog extends Model
{
    protected $connection = 'mysql';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'method',
        'url',
        'error_class',
        'error_message',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('occurred_at', '>=', now('UTC')->subDays($days));
    }
}
