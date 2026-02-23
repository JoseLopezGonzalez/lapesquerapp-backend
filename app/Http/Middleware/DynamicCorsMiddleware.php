<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dynamic CORS middleware.
 *
 * - Local/testing: delegates to Laravel's HandleCors with the static config.
 * - Production: validates the Origin header dynamically against active tenants
 *   in the central DB (cached 10 min per subdomain).
 */
class DynamicCorsMiddleware
{
    private array $corsConfig;

    public function __construct()
    {
        $this->corsConfig = config('cors');
    }

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if (!$origin) {
            return $next($request);
        }

        if (!$this->pathMatches($request)) {
            $response = $next($request);
            $response->headers->set('Vary', 'Origin');
            return $response;
        }

        if ($this->isLocalEnv()) {
            return $this->handleWithStatic($request, $next, $origin);
        }

        return $this->handleDynamic($request, $next, $origin);
    }

    /**
     * Only apply CORS logic to paths configured in cors.php (e.g. api/*, sanctum/csrf-cookie).
     */
    private function pathMatches(Request $request): bool
    {
        $paths = $this->corsConfig['paths'] ?? [];
        $requestPath = $request->path();

        foreach ($paths as $pattern) {
            if ($pattern === $requestPath || fnmatch($pattern, $requestPath)) {
                return true;
            }
        }

        return false;
    }

    private function isLocalEnv(): bool
    {
        return in_array(config('app.env'), ['local', 'testing'], true);
    }

    /**
     * Local/testing: use the static allowed_origins + allowed_origins_patterns
     * from config/cors.php (same behavior as HandleCors).
     */
    private function handleWithStatic(Request $request, Closure $next, string $origin): Response
    {
        if (!$this->originMatchesStatic($origin)) {
            return $this->rejectCors($request, $next);
        }

        $response = $request->isMethod('OPTIONS')
            ? response('', 204)
            : $next($request);

        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Production: check Origin against fixed origins first, then resolve
     * the subdomain dynamically against the tenants table (cached).
     */
    private function handleDynamic(Request $request, Closure $next, string $origin): Response
    {
        if ($this->originMatchesStatic($origin)) {
            $response = $request->isMethod('OPTIONS')
                ? response('', 204)
                : $next($request);

            return $this->addCorsHeaders($response, $origin);
        }

        $subdomain = $this->extractSubdomain($origin);

        if (!$subdomain || !$this->isActiveTenant($subdomain)) {
            return $this->rejectCors($request, $next);
        }

        $response = $request->isMethod('OPTIONS')
            ? response('', 204)
            : $next($request);

        return $this->addCorsHeaders($response, $origin);
    }

    private function originMatchesStatic(string $origin): bool
    {
        $allowed = $this->corsConfig['allowed_origins'] ?? [];
        if (in_array($origin, $allowed, true)) {
            return true;
        }

        $patterns = $this->corsConfig['allowed_origins_patterns'] ?? [];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the subdomain from an origin URL given the configured base domains.
     * E.g. https://brisamar.lapesquerapp.es -> brisamar
     */
    private function extractSubdomain(string $origin): ?string
    {
        $baseDomains = array_filter(array_map(
            'trim',
            explode(',', config('cors.base_domains', 'lapesquerapp.es'))
        ));

        $parsed = parse_url($origin);
        $host = $parsed['host'] ?? '';

        foreach ($baseDomains as $base) {
            $suffix = '.' . $base;
            if (str_ends_with($host, $suffix)) {
                $sub = substr($host, 0, -strlen($suffix));
                if ($sub !== '' && !str_contains($sub, '.')) {
                    return $sub;
                }
            }
        }

        return null;
    }

    private function isActiveTenant(string $subdomain): bool
    {
        return Cache::remember(
            "cors:tenant:{$subdomain}",
            600,
            fn () => Tenant::active()->where('subdomain', $subdomain)->exists()
        );
    }

    private function addCorsHeaders(Response $response, string $origin): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->corsConfig['allowed_methods'] ?? ['*']));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', $this->corsConfig['allowed_headers'] ?? ['*']));
        $response->headers->set('Access-Control-Max-Age', (string) ($this->corsConfig['max_age'] ?? 0));
        $response->headers->set('Vary', 'Origin');

        if ($this->corsConfig['supports_credentials'] ?? false) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $exposed = $this->corsConfig['exposed_headers'] ?? [];
        if (!empty($exposed)) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $exposed));
        }

        return $response;
    }

    /**
     * For non-preflight requests from unknown origins: proceed without CORS headers.
     * For OPTIONS preflight: return 204 without CORS headers (browser blocks).
     */
    private function rejectCors(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204);
        }

        return $next($request);
    }
}
