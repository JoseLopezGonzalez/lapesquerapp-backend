<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProfitabilityStatJob extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    public const TYPE_SUMMARY = 'summary';

    public const TYPE_PRODUCTS = 'products';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'type',
        'created_by_user_id',
        'status',
        'filters',
        'result',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED && $this->result !== null;
    }
}
