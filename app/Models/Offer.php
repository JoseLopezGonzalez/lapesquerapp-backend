<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const SEND_CHANNEL_EMAIL = 'email';

    public const SEND_CHANNEL_PDF = 'pdf';

    public const SEND_CHANNEL_WHATSAPP_TEXT = 'whatsapp_text';

    protected $fillable = [
        'prospect_id',
        'customer_id',
        'salesperson_id',
        'status',
        'send_channel',
        'sent_at',
        'valid_until',
        'incoterm_id',
        'payment_term_id',
        'currency',
        'notes',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'order_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'valid_until' => 'date:Y-m-d',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
        ];
    }

    public static function sendChannels(): array
    {
        return [
            self::SEND_CHANNEL_EMAIL,
            self::SEND_CHANNEL_PDF,
            self::SEND_CHANNEL_WHATSAPP_TEXT,
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

    public function incoterm()
    {
        return $this->belongsTo(Incoterm::class);
    }

    public function paymentTerm()
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function lines()
    {
        return $this->hasMany(OfferLine::class);
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'prospectId' => $this->prospect_id,
            'customerId' => $this->customer_id,
            'prospect' => $this->relationLoaded('prospect') ? $this->prospect?->toArrayAssoc() : null,
            'customer' => $this->relationLoaded('customer') ? $this->customer?->toArrayAssoc() : null,
            'salesperson' => $this->relationLoaded('salesperson') ? $this->salesperson?->toArrayAssoc() : null,
            'status' => $this->status,
            'sendChannel' => $this->send_channel,
            'sentAt' => $this->sent_at?->toISOString(),
            'validUntil' => $this->valid_until?->format('Y-m-d'),
            'incoterm' => $this->relationLoaded('incoterm') ? $this->incoterm?->toArrayAssoc() : null,
            'paymentTerm' => $this->relationLoaded('paymentTerm') ? $this->paymentTerm?->toArrayAssoc() : null,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'acceptedAt' => $this->accepted_at?->toISOString(),
            'rejectedAt' => $this->rejected_at?->toISOString(),
            'rejectionReason' => $this->rejection_reason,
            'orderId' => $this->order_id,
            'lines' => $this->relationLoaded('lines')
                ? $this->lines->map(fn (OfferLine $line) => $line->toArrayAssoc())->values()
                : [],
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
