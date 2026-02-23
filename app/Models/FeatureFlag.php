<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'flag_key',
        'plan',
        'enabled',
        'description',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
