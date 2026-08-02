<?php

namespace App\Http\Controllers\v2;

use App\Enums\Role;
use App\Http\Controllers\Controller;

class RoleController extends Controller
{
    /**
     * Opciones de roles para selects (desde enum, sin BD).
     */
    public function options()
    {
        return response()->json(Role::optionsForApi());
    }
}
