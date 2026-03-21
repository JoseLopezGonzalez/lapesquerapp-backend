<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteTemplateStop extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'route_template_id',
        'position',
        'stop_type',
        'target_type',
        'customer_id',
        'prospect_id',
        'label',
        'address',
        'notes',
    ];

    public function routeTemplate()
    {
        return $this->belongsTo(RouteTemplate::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }
}
