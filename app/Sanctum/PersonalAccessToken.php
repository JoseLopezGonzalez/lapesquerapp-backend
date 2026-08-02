<?php

namespace App\Sanctum;

use App\Traits\UsesTenantConnection;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use UsesTenantConnection;
}
