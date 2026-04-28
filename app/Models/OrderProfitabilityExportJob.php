<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProfitabilityExportJob extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'created_by_user_id',
        'status',
        'filters',
        'filename',
        'file_path',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED && $this->file_path !== null;
    }
}
