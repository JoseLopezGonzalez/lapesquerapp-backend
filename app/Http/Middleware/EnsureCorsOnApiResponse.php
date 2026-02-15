<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asegura que las respuestas de la API lleven cabeceras CORS cuando el origen está permitido.
 * Refuerza a HandleCors por si el proxy no reenvía Origin o la config no se aplica.
 */
class EnsureCorsOnApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        if ($response->headers->has('Access-Control-Allow-Origin')) {
            return $response;
        }

        $origin = $request->header('Origin');
        if (! $origin || ! $this->isOriginAllowed($origin)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'])));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', (array) config('cors.allowed_headers', ['*'])));
        if (config('cors.supports_credentials', false)) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        $response->headers->set('Vary', trim(($response->headers->get('Vary') ? $response->headers->get('Vary') . ', ' : '') . 'Origin'));

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        $allowed = config('cors.allowed_origins', []);
        if (in_array($origin, $allowed, true)) {
            return true;
        }
        $patterns = config('cors.allowed_origins_patterns', []);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }
        return false;
    }
}
