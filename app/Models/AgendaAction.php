<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaAction extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'target_type',
        'target_id',
        'scheduled_at',
        'description',
        'status',
        'reason',
        'source_interaction_id',
        'completed_interaction_id',
        'previous_action_id',
    ];

    protected $casts = [
        'scheduled_at' => 'date:Y-m-d',
    ];

    public function toArrayAssoc(): array
    {
        return [
            'agendaActionId' => $this->id,
            'targetType' => $this->target_type,
            'targetId' => $this->target_id,
            'scheduledAt' => $this->scheduled_at?->format('Y-m-d'),
            'description' => $this->description,
            'status' => $this->status,
            'reason' => $this->reason,
            'previousActionId' => $this->previous_action_id,
        ];
    }
}
