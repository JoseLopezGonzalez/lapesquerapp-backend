<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialInteraction extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    public const TYPE_CALL = 'call';

    public const TYPE_EMAIL = 'email';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_VISIT = 'visit';

    public const TYPE_OTHER = 'other';

    public const RESULT_INTERESTED = 'interested';

    public const RESULT_NO_RESPONSE = 'no_response';

    public const RESULT_NOT_INTERESTED = 'not_interested';

    public const RESULT_PENDING = 'pending';

    public $timestamps = false;

    protected $fillable = [
        'prospect_id',
        'customer_id',
        'salesperson_id',
        'type',
        'occurred_at',
        'summary',
        'result',
        'next_action_note',
        'next_action_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'next_action_at' => 'date:Y-m-d',
        'created_at' => 'datetime',
    ];

    public static function types(): array
    {
        return [
            self::TYPE_CALL,
            self::TYPE_EMAIL,
            self::TYPE_WHATSAPP,
            self::TYPE_VISIT,
            self::TYPE_OTHER,
        ];
    }

    public static function results(): array
    {
        return [
            self::RESULT_INTERESTED,
            self::RESULT_NO_RESPONSE,
            self::RESULT_NOT_INTERESTED,
            self::RESULT_PENDING,
        ];
    }

    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesperson()
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'prospectId' => $this->prospect_id,
            'customerId' => $this->customer_id,
            'isFromProspect' => $this->prospect_id !== null,
            'salesperson' => $this->relationLoaded('salesperson') ? $this->salesperson?->toArrayAssoc() : null,
            'type' => $this->type,
            'occurredAt' => $this->occurred_at?->toISOString(),
            'summary' => $this->summary,
            'result' => $this->result,
            'nextActionNote' => $this->next_action_note,
            'nextActionAt' => $this->next_action_at?->format('Y-m-d'),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
