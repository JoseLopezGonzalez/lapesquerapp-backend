<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    /** @var string Conexión a la base central (tabla tenants). */
    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'subdomain',
        'database',
        'status',
        'plan',
        'renewal_at',
        'timezone',
        'branding_image_url',
        'last_activity_at',
        'onboarding_step',
        'admin_email',
    ];

    protected $casts = [
        'status' => 'string',
        'renewal_at' => 'date',
        'last_activity_at' => 'datetime',
        'onboarding_step' => 'integer',
    ];

    /** Backward-compatible accessor: $tenant->is_active */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
