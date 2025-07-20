<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{

    protected $fillable = [
        'name',
        'subdomain',
        'database',
        'active',
        'branding_image_url',
    ];

    protected $casts = [
        'active' => 'boolean',
        'branding_image_url' => 'string',
    ];
}
