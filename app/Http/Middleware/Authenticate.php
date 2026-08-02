<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Evitar redirección a login, devolviendo null siempre.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null; // Nunca redirige a login
    }
}
