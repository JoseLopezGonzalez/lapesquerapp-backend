<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Superadmin\FeatureFlagService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureFlag
{
    public function __construct(
        private FeatureFlagService $service
    ) {}

    /**
     * Usage: Route::middleware('feature:module.production')
     */
    public function handle(Request $request, Closure $next, string $flagKey): Response
    {
        if (!app()->bound('currentTenant') || !app('currentTenant')) {
            return response()->json([
                'message'     => 'Contexto de tenant no encontrado.',
                'userMessage' => 'Acceso no permitido.',
            ], 403);
        }

        $subdomain = app('currentTenant');
        $tenant    = Tenant::where('subdomain', $subdomain)->first();

        if (!$tenant) {
            return response()->json([
                'message'     => 'Tenant no encontrado.',
                'userMessage' => 'Acceso no permitido.',
            ], 403);
        }

        if (!$this->service->isEnabled($tenant, $flagKey)) {
            return response()->json([
                'message'     => "La funcionalidad '{$flagKey}' no está disponible en tu plan.",
                'userMessage' => 'Esta funcionalidad no está incluida en tu plan actual. Contacta con tu administrador para actualizar.',
                'flag'        => $flagKey,
            ], 403);
        }

        return $next($request);
    }
}
