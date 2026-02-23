<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeatureOverride extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'flag_key',
        'enabled',
        'overridden_by_superadmin_id',
        'reason',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(SuperadminUser::class, 'overridden_by_superadmin_id');
    }
}
