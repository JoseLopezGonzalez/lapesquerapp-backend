<?php

namespace App\Http\Middleware;

use App\Models\ExternalUser;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAllowedActor
{
    public function handle(Request $request, Closure $next, string ...$allowedActors): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $isInternalAllowed = in_array('internal', $allowedActors, true);
        $isExternalAllowed = in_array('external', $allowedActors, true);

        $allowed = ($isInternalAllowed && $user instanceof User)
            || ($isExternalAllowed && $user instanceof ExternalUser);

        if (! $allowed) {
            return response()->json([
                'message' => 'Acción no autorizada.',
                'userMessage' => 'No tienes permiso para acceder a esta ruta.',
            ], 403);
        }

        return $next($request);
    }
}
