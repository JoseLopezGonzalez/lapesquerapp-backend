<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationLog extends Model
{
    protected $connection = 'mysql';

    public $timestamps = false;

    protected $fillable = [
        'superadmin_user_id',
        'tenant_id',
        'target_user_id',
        'mode',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function superadminUser(): BelongsTo
    {
        return $this->belongsTo(SuperadminUser::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
