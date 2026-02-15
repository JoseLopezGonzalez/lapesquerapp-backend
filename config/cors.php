<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Orígenes explícitos desde env (comma-separated). En producción: lista blanca.
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:3001,http://127.0.0.1:3000,http://127.0.0.1:3001,http://localhost:5173')))),

    // Patrones regex para subdominios dinámicos (multi-tenant)
    'allowed_origins_patterns' => [
        '/^https:\/\/[a-z0-9\-]+\.lapesquerapp\.es$/',
        '/^https:\/\/lapesquerapp\.es$/',
        '/^https:\/\/[a-z0-9\-]+\.congeladosbrisamar\.es$/',
        '/^http:\/\/[a-z0-9\-]+\.localhost(:\d+)?$/',
        '/^http:\/\/127\.0\.0\.1(:\d+)?$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
