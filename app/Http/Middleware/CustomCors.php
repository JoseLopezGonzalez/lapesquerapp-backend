<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom CORS middleware — replaces Laravel's HandleCors.
 *
 * Reads all settings from config/cors.php (source of truth).
 * Handles preflight OPTIONS with 204 and adds CORS headers to every API response.
 */
class CustomCors
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply CORS to paths that match config
        if (! $this->hasMatchingPath($request)) {
            return $next($request);
        }

        $origin = $request->header('Origin');
        $isAllowed = $origin && $this->isOriginAllowed($origin);

        // Preflight OPTIONS → return 204 immediately
        if ($request->isMethod('OPTIONS')) {
            return $this->preflightResponse($request, $origin, $isAllowed);
        }

        // Normal request → pass through, then add CORS headers
        $response = $next($request);

        if ($isAllowed) {
            $this->addCorsHeaders($response, $origin);
        }

        return $response;
    }

    /**
     * Build a 204 preflight response with full CORS headers.
     */
    private function preflightResponse(Request $request, ?string $origin, bool $isAllowed): Response
    {
        $response = response('', 204);

        if (! $isAllowed) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);

        // Methods
        $methods = config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH']);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $methods));

        // Headers — echo back what the client requested when configured as wildcard
        $configHeaders = config('cors.allowed_headers', ['*']);
        if ($configHeaders === ['*'] && $request->headers->has('Access-Control-Request-Headers')) {
            $response->headers->set(
                'Access-Control-Allow-Headers',
                $request->header('Access-Control-Request-Headers')
            );
        } else {
            $response->headers->set(
                'Access-Control-Allow-Headers',
                is_array($configHeaders) ? implode(', ', $configHeaders) : $configHeaders
            );
        }

        // Max-Age
        $maxAge = config('cors.max_age', 86400);
        $response->headers->set('Access-Control-Max-Age', (string) $maxAge);

        // Credentials
        if (config('cors.supports_credentials', false)) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Vary', 'Origin');

        return $response;
    }

    /**
     * Add CORS headers to a normal (non-preflight) response.
     */
    private function addCorsHeaders(Response $response, string $origin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);

        if (config('cors.supports_credentials', false)) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        // Exposed headers
        $exposed = config('cors.exposed_headers', []);
        if (! empty($exposed)) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $exposed));
        }

        // Vary so caches key on Origin
        $vary = $response->headers->get('Vary');
        if ($vary && ! str_contains($vary, 'Origin')) {
            $response->headers->set('Vary', $vary . ', Origin');
        } elseif (! $vary) {
            $response->headers->set('Vary', 'Origin');
        }
    }

    /**
     * Check if the request path matches any path in cors.paths config.
     */
    private function hasMatchingPath(Request $request): bool
    {
        $paths = config('cors.paths', []);

        foreach ($paths as $path) {
            if ($path !== '/') {
                $path = trim($path, '/');
            }

            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the given origin is allowed by config.
     */
    private function isOriginAllowed(string $origin): bool
    {
        $allowed = config('cors.allowed_origins', []);

        // Wildcard
        if (in_array('*', $allowed, true)) {
            return true;
        }

        // Exact match
        if (in_array($origin, $allowed, true)) {
            return true;
        }

        // Pattern match
        foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }
}
