<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class Caliber extends Model
{
    use UsesTenantConnection;

    protected $table = 'calibers';

    protected $fillable = [
        'name',
        'min_weight',
        'max_weight',
        'species',
    ];

    protected $casts = [
        'min_weight' => 'integer',
        'max_weight' => 'integer',
    ];
}
