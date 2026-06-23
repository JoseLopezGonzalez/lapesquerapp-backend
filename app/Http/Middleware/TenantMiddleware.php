<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantBlocklist;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $request->header('X-Tenant');

        if (!$subdomain) {
            return response()->json(['error' => 'Tenant not specified'], 400);
        }

        $tenant = Cache::remember("tenant_mw:{$subdomain}", 300, function () use ($subdomain) {
            return Tenant::where('subdomain', $subdomain)->first();
        });

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        if ($tenant->status === 'suspended') {
            return response()->json([
                'error' => 'Cuenta suspendida',
                'userMessage' => 'Tu cuenta está suspendida. Contacta con soporte.',
                'status' => 'suspended',
            ], 403);
        }

        if ($tenant->status !== 'active') {
            return response()->json(['error' => 'Tenant not available'], 403);
        }

        // Verificar si la IP del cliente está en la blocklist activa del tenant
        $clientIp = $request->ip();
        $isIpBlocked = Cache::remember(
            "blocklist:{$tenant->id}:ip:{$clientIp}",
            300,
            fn () => TenantBlocklist::where('tenant_id', $tenant->id)
                ->where('type', 'ip')
                ->where('value', $clientIp)
                ->active()
                ->exists()
        );

        if ($isIpBlocked) {
            return response()->json([
                'error' => 'Acceso bloqueado',
                'userMessage' => 'Tu acceso ha sido restringido. Contacta con soporte.',
            ], 403);
        }

        app()->instance('currentTenant', $subdomain);
        app()->instance('currentTenantId', $tenant->id);

        config([
            'database.default' => 'tenant',
            'database.connections.tenant.database' => $tenant->database,
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');

        // Actualizar last_activity_at con throttle (máximo una vez cada 5 minutos por tenant)
        $activityKey = "tenant_activity:{$tenant->id}";
        if (!Cache::has($activityKey)) {
            Cache::put($activityKey, true, 300);
            DB::connection('mysql')->table('tenants')
                ->where('id', $tenant->id)
                ->update(['last_activity_at' => now('UTC')]);
        }

        if (config('app.debug')) {
            Log::info('Tenant connection established', [
                'subdomain' => $subdomain,
                'database'  => $tenant->database,
            ]);
        }

        return $next($request);
    }
}
