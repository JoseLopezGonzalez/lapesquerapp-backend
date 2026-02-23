<?php

namespace App\Sanctum;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class SuperadminPersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'mysql';

    protected $table = 'superadmin_personal_access_tokens';
}
