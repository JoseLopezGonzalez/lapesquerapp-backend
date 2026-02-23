<?php

namespace App\Models;

use App\Sanctum\SuperadminPersonalAccessToken;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class SuperadminUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $connection = 'mysql';

    protected $table = 'superadmin_users';

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'last_login_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    /**
     * Sanctum tokens for superadmin live in the central DB,
     * not in the tenant DB used by the default PersonalAccessToken.
     */
    public function tokens()
    {
        return $this->morphMany(SuperadminPersonalAccessToken::class, 'tokenable');
    }
}
