<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteStop extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';

    public const STOP_TYPE_REQUIRED = 'obligatoria';
    public const STOP_TYPE_SUGGESTED = 'sugerida';
    public const STOP_TYPE_OPPORTUNITY = 'oportunidad';

    public const RESULT_TYPE_DELIVERY = 'delivery';
    public const RESULT_TYPE_AUTOVENTA = 'autoventa';
    public const RESULT_TYPE_NO_CONTACT = 'no_contact';
    public const RESULT_TYPE_INCIDENT = 'incident';
    public const RESULT_TYPE_VISIT = 'visit';

    protected $fillable = [
        'route_id',
        'route_template_stop_id',
        'position',
        'stop_type',
        'target_type',
        'customer_id',
        'prospect_id',
        'label',
        'address',
        'notes',
        'status',
        'result_type',
        'result_notes',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public static function validStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_COMPLETED,
            self::STATUS_SKIPPED,
        ];
    }

    public static function validStopTypes(): array
    {
        return [
            self::STOP_TYPE_REQUIRED,
            self::STOP_TYPE_SUGGESTED,
            self::STOP_TYPE_OPPORTUNITY,
        ];
    }

    public static function validResultTypes(): array
    {
        return [
            self::RESULT_TYPE_DELIVERY,
            self::RESULT_TYPE_AUTOVENTA,
            self::RESULT_TYPE_NO_CONTACT,
            self::RESULT_TYPE_INCIDENT,
            self::RESULT_TYPE_VISIT,
        ];
    }

    public function route()
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }

    public function routeTemplateStop()
    {
        return $this->belongsTo(RouteTemplateStop::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'route_stop_id');
    }
}
