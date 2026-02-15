<?php

namespace App\Http\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Utilidad para añadir cabeceras CORS a respuestas de la API.
 * Usar en controladores de rutas públicas (p. ej. tenant) cuando el proxy
 * pueda no reenviar Origin o el middleware no aplique.
 */
final class CorsResponse
{
    /**
     * Añade cabeceras CORS a la respuesta si el Origin de la petición está permitido.
     * No sobrescribe si la respuesta ya tiene Access-Control-Allow-Origin.
     *
     * @param  Request  $request
     * @param  Response|SymfonyResponse  $response
     * @return Response|SymfonyResponse
     */
    public static function addToResponse(Request $request, $response)
    {
        if ($response->headers->has('Access-Control-Allow-Origin')) {
            return $response;
        }

        $origin = $request->header('Origin');
        if (! $origin || ! self::isOriginAllowed($origin)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'])));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', (array) config('cors.allowed_headers', ['*'])));
        if (config('cors.supports_credentials', false)) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        $vary = $response->headers->get('Vary');
        $response->headers->set('Vary', trim(($vary ? $vary . ', ' : '') . 'Origin'));

        return $response;
    }

    /**
     * Respuesta 204 No Content con cabeceras CORS para preflight OPTIONS.
     *
     * @param  Request  $request
     * @return Response
     */
    public static function preflightResponse(Request $request): Response
    {
        $response = response('', 204);
        $origin = $request->header('Origin');
        if ($origin && self::isOriginAllowed($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', implode(', ', config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'])));
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', (array) config('cors.allowed_headers', ['*'])));
            $response->headers->set('Access-Control-Max-Age', (string) (config('cors.max_age', 0) ?: 86400));
            if (config('cors.supports_credentials', false)) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            $response->headers->set('Vary', 'Origin');
        }
        return $response;
    }

    public static function isOriginAllowed(string $origin): bool
    {
        $allowed = config('cors.allowed_origins', []);
        if (in_array($origin, $allowed, true)) {
            return true;
        }
        foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }
        return false;
    }
}
