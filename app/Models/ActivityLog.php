<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ip_address',
        'country',
        'city',
        'region',
        'platform',
        'browser',
        'device',
        'path',
        'method',
        'location',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
