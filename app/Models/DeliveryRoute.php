<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryRoute extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'routes';

    protected $fillable = [
        'route_template_id',
        'name',
        'description',
        'route_date',
        'status',
        'salesperson_id',
        'field_operator_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'route_date' => 'date',
    ];

    public static function validStatuses(): array
    {
        return [
            self::STATUS_PLANNED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    public function routeTemplate()
    {
        return $this->belongsTo(RouteTemplate::class);
    }

    public function salesperson()
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function fieldOperator()
    {
        return $this->belongsTo(FieldOperator::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function stops()
    {
        return $this->hasMany(RouteStop::class, 'route_id')->orderBy('position');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'route_id');
    }
}
