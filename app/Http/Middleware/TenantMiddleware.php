<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $request->header('X-Tenant');

        if (!$subdomain) {
            return response()->json(['error' => 'Tenant not specified'], 400);
        }

        $tenant = Tenant::where('subdomain', $subdomain)->first();

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

        app()->instance('currentTenant', $subdomain);

        config([
            'database.connections.tenant.database' => $tenant->database,
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');
        DB::connection('tenant')->statement("SET time_zone = '+00:00'");

        if (config('app.debug')) {
            Log::info('Tenant connection established', [
                'subdomain' => $subdomain,
                'database'  => $tenant->database,
            ]);
        }

        return $next($request);
    }
}
