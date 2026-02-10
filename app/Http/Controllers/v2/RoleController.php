<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Enums\Role;

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
