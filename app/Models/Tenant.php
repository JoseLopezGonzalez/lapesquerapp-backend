<?php

namespace App\Models;

use App\Services\Superadmin\TenantOnboardingService;
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
        'onboarding_error',
        'onboarding_failed_at',
        'admin_email',
    ];

    protected $casts = [
        'status' => 'string',
        'renewal_at' => 'date',
        'last_activity_at' => 'datetime',
        'onboarding_step' => 'integer',
        'onboarding_failed_at' => 'datetime',
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

    /**
     * Computed onboarding status for the frontend:
     * completed | failed | in_progress | pending
     */
    public function getOnboardingStatusAttribute(): string
    {
        if ($this->onboarding_step >= TenantOnboardingService::TOTAL_STEPS) {
            return 'completed';
        }

        if ($this->onboarding_failed_at !== null) {
            return 'failed';
        }

        if ($this->onboarding_step > 0) {
            return 'in_progress';
        }

        return 'pending';
    }

    /**
     * Human-readable label for the current onboarding step.
     */
    public function getOnboardingStepLabelAttribute(): ?string
    {
        return TenantOnboardingService::stepLabel($this->onboarding_step ?? 0);
    }
}
