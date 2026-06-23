<?php

namespace App\Http\Middleware;

use App\Models\TenantBlocklist;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantEmailBlocklist
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        if (!$user || !$tenantId) {
            return $next($request);
        }

        $isEmailBlocked = Cache::remember(
            "blocklist:{$tenantId}:email:{$user->email}",
            300,
            fn () => TenantBlocklist::where('tenant_id', $tenantId)
                ->where('type', 'email')
                ->where('value', $user->email)
                ->active()
                ->exists()
        );

        if ($isEmailBlocked) {
            return response()->json([
                'error' => 'Acceso bloqueado',
                'userMessage' => 'Tu acceso ha sido restringido. Contacta con soporte.',
            ], 403);
        }

        return $next($request);
    }
}
