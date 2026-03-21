<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteTemplate extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'name',
        'description',
        'salesperson_id',
        'field_operator_id',
        'created_by_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

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
        return $this->hasMany(RouteTemplateStop::class)->orderBy('position');
    }
}
