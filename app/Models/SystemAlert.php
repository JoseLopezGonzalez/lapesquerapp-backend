<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAlert extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'type',
        'severity',
        'tenant_id',
        'message',
        'metadata',
        'resolved_at',
        'resolved_by_superadmin_id',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'resolved_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(SuperadminUser::class, 'resolved_by_superadmin_id');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /**
     * Create an alert only if there is no existing unresolved alert of the same type for the same tenant.
     */
    public static function createIfNotExists(
        string $type,
        string $severity,
        string $message,
        ?int $tenantId = null,
        ?array $metadata = null
    ): ?self {
        $existing = self::query()
            ->where('type', $type)
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->first();

        if ($existing) {
            return null;
        }

        return self::create([
            'type'      => $type,
            'severity'  => $severity,
            'message'   => $message,
            'tenant_id' => $tenantId,
            'metadata'  => $metadata,
        ]);
    }
}
