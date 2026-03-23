<?php

namespace App\Http\Middleware;

use App\Models\ExternalUser;
use App\Services\AuthActorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExternalUserIsActive
{
    public function __construct(
        protected AuthActorService $actors
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof ExternalUser) {
            $user->refresh();
        }

        if ($user instanceof ExternalUser && ! $user->is_active) {
            $this->actors->revokeTokens($user);

            return response()->json([
                'message' => 'Acción no autorizada.',
                'userMessage' => 'Tu acceso externo está desactivado. Contacta con el tenant.',
            ], 403);
        }

        return $next($request);
    }
}
