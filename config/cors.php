<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Single source of truth for CORS. Laravel's HandleCors middleware reads
    | this file and applies it to paths matched by the 'paths' key below.
    |
    | Do NOT add CORS headers in .htaccess, Dockerfile, Apache config, or
    | custom middleware. All CORS logic lives here.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins (exact match)
    |--------------------------------------------------------------------------
    |
    | Exact, literal origins. Use for URLs that don't follow a wildcard
    | pattern. Do NOT put wildcard entries here — use
    | allowed_origins_patterns for those.
    |
    */
    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'https://lapesquerapp.es',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origin Patterns (regex)
    |--------------------------------------------------------------------------
    |
    | Regex patterns for dynamic subdomains. These are checked when the
    | origin does not match any exact entry in allowed_origins.
    |
    */
    'allowed_origins_patterns' => [
        '#^https://[a-z0-9\-]+\.lapesquerapp\.es\z#',
        '#^https://[a-z0-9\-]+\.congeladosbrisamar\.es\z#',
        '#^http://[a-z0-9\-]+\.localhost:3000\z#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Max Age (seconds)
    |--------------------------------------------------------------------------
    |
    | How long browsers cache preflight responses. 86400 = 24 hours.
    | Chrome caps at 7200 (2h), Firefox honors 86400 (24h).
    |
    */
    'max_age' => 86400,

    'supports_credentials' => true,

];
