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

        $excluded = [
            'api/v2/public/*',
            'api/health',
        ];

        foreach ($excluded as $route) {
            if ($request->is($route)) {
                return $next($request);
            }
        }

        // Preflight OPTIONS no incluye X-Tenant; debe pasar para que HandleCors responda con CORS
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $subdomain = $request->header('X-Tenant');

        if (!$subdomain) {
            return response()->json(['error' => 'Tenant not specified'], 400);
        }

        // Buscamos el tenant en la base central
        $tenant = Tenant::where('subdomain', $subdomain)->where('active', true)->first();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found or inactive'], 404);
        }

        // Guardamos el subdominio actual globalmente
        app()->instance('currentTenant', $subdomain);

        // Configuramos la conexión dinámica para el tenant
        config([
            'database.connections.tenant.database' => $tenant->database,
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');

        // Log solo en debug para evitar I/O y volumen en producción
        if (config('app.debug')) {
            Log::info('🔁 Conexión cambiada dinámicamente a tenant', [
                'subdomain' => $subdomain,
                'database' => $tenant->database,
            ]);
        }

        return $next($request);
    }
}
