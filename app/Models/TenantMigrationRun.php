<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMigrationRun extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'triggered_by_superadmin_id',
        'migrations_applied',
        'output',
        'success',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'success'     => 'boolean',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function superadminUser(): BelongsTo
    {
        return $this->belongsTo(SuperadminUser::class, 'triggered_by_superadmin_id');
    }
}
