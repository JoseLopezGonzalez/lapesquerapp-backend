<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBlocklist extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'type',
        'value',
        'blocked_by_superadmin_id',
        'reason',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(SuperadminUser::class, 'blocked_by_superadmin_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now('UTC'));
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
