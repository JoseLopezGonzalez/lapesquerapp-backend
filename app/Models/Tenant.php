<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    /** @var string Conexión a la base central (tabla tenants). */
    protected $connection = 'mysql';

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
