<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    use HasFactory;
    use UsesTenantConnection;
    protected $fillable = ['name', 'format'];

    protected $casts = [
        'format' => 'array',
    ];
}
