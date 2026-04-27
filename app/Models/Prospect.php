<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prospect extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    public const STATUS_NEW = 'new';

    public const STATUS_FOLLOWING = 'following';

    public const STATUS_OFFER_SENT = 'offer_sent';

    public const STATUS_CUSTOMER = 'customer';

    public const STATUS_DISCARDED = 'discarded';

    public const ORIGIN_CONXEMAR = 'conxemar';

    public const ORIGIN_DIRECT = 'direct';

    public const ORIGIN_REFERRAL = 'referral';

    public const ORIGIN_WEB = 'web';

    public const ORIGIN_INBOUND_CALL = 'inbound_call';

    public const ORIGIN_EMAIL = 'email';

    public const ORIGIN_WEB_FORM = 'web_form';

    public const ORIGIN_WHATSAPP = 'whatsapp';

    public const ORIGIN_LINKEDIN = 'linkedin';

    public const ORIGIN_EVENT = 'event';

    public const ORIGIN_AGENT = 'agent';

    public const ORIGIN_MARKETING_CAMPAIGN = 'marketing_campaign';

    public const ORIGIN_REACTIVATION = 'reactivation';

    public const ORIGIN_ONLINE_SEARCH = 'online_search';

    public const ORIGIN_GOOGLE_MAPS = 'google_maps';

    public const ORIGIN_AI_SOURCED = 'ai_sourced';

    public const ORIGIN_OTHER = 'other';

    protected $fillable = [
        'salesperson_id',
        'category_id',
        'company_name',
        'address',
        'website',
        'country_id',
        'species_interest',
        'origin',
        'status',
        'customer_id',
        'next_action_at',
        'next_action_note',
        'notes',
        'commercial_interest_notes',
        'last_contact_at',
        'last_offer_at',
        'lost_reason',
    ];

    protected $casts = [
        'species_interest' => 'array',
        'next_action_at' => 'date:Y-m-d',
        'last_contact_at' => 'datetime',
        'last_offer_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_FOLLOWING,
            self::STATUS_OFFER_SENT,
            self::STATUS_CUSTOMER,
            self::STATUS_DISCARDED,
        ];
    }

    public static function origins(): array
    {
        return [
            self::ORIGIN_CONXEMAR,
            self::ORIGIN_DIRECT,
            self::ORIGIN_REFERRAL,
            self::ORIGIN_WEB,
            self::ORIGIN_INBOUND_CALL,
            self::ORIGIN_EMAIL,
            self::ORIGIN_WEB_FORM,
            self::ORIGIN_WHATSAPP,
            self::ORIGIN_LINKEDIN,
            self::ORIGIN_EVENT,
            self::ORIGIN_AGENT,
            self::ORIGIN_MARKETING_CAMPAIGN,
            self::ORIGIN_REACTIVATION,
            self::ORIGIN_ONLINE_SEARCH,
            self::ORIGIN_GOOGLE_MAPS,
            self::ORIGIN_AI_SOURCED,
            self::ORIGIN_OTHER,
        ];
    }

    public function salesperson()
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function category()
    {
        return $this->belongsTo(ProspectCategory::class, 'category_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function contacts()
    {
        return $this->hasMany(ProspectContact::class);
    }

    public function primaryContact()
    {
        return $this->hasOne(ProspectContact::class)->where('is_primary', true);
    }

    public function interactions()
    {
        return $this->hasMany(CommercialInteraction::class);
    }

    public function latestInteraction()
    {
        return $this->hasOne(CommercialInteraction::class)->latestOfMany('occurred_at');
    }

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    public function toArrayAssoc(): array
    {
        $offers = $this->relationLoaded('offers') ? $this->offers : collect();

        return [
            'id' => $this->id,
            'companyName' => $this->company_name,
            'categoryId' => $this->category_id,
            'category' => $this->relationLoaded('category') ? $this->category?->toArrayAssoc() : null,
            'address' => $this->address,
            'website' => $this->website,
            'country' => $this->relationLoaded('country') ? $this->country?->toArrayAssoc() : null,
            'speciesInterest' => $this->species_interest ?? [],
            'origin' => $this->origin,
            'status' => $this->status,
            'salesperson' => $this->relationLoaded('salesperson') ? $this->salesperson?->toArrayAssoc() : null,
            'customer' => $this->relationLoaded('customer') ? $this->customer?->toArrayAssoc() : null,
            'nextActionAt' => $this->next_action_at?->format('Y-m-d'),
            'nextActionNote' => $this->next_action_note,
            'notes' => $this->notes,
            'commercialInterestNotes' => $this->commercial_interest_notes,
            'lastContactAt' => $this->last_contact_at?->toISOString(),
            'lastOfferAt' => $this->last_offer_at?->toISOString(),
            'lostReason' => $this->lost_reason,
            'primaryContact' => $this->relationLoaded('primaryContact') ? $this->primaryContact?->toArrayAssoc() : null,
            'latestInteraction' => $this->relationLoaded('latestInteraction') ? $this->latestInteraction?->toArrayAssoc() : null,
            'contacts' => $this->relationLoaded('contacts')
                ? $this->contacts->map(fn (ProspectContact $contact) => $contact->toArrayAssoc())->values()
                : [],
            'interactions' => $this->relationLoaded('interactions')
                ? $this->interactions->map(fn (CommercialInteraction $interaction) => $interaction->toArrayAssoc())->values()
                : [],
            'offers' => $this->relationLoaded('offers')
                ? $this->offers->map(fn (Offer $offer) => $offer->toArrayAssoc())->values()
                : [],
            'offersSummary' => [
                'count' => $offers->count(),
                'latestStatus' => $offers->sortByDesc('created_at')->first()?->status,
            ],
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
