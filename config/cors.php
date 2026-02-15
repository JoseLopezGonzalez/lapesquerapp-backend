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

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],


    // Permitir varios orígenes: env CORS_ALLOWED_ORIGINS (producción) + desarrollo
    'allowed_origins' => array_filter(array_merge(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))),
        [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:3001',
            'http://localhost:5173',
            'https://*.congeladosbrisamar.es',
            'https://lapesquerapp.es',
            'https://*.lapesquerapp.es',
            'http://brisamar.localhost:3000',
            'http://test.localhost:3000',
            'http://pymcolorao.localhost:3000',
        ]
    )),

    'allowed_origins_patterns' => [
        '/^https:\/\/[a-z0-9\-]+\.lapesquerapp\.es$/',
        '/^https:\/\/[a-z0-9\-]+\.congeladosbrisamar\.es$/',
        '/^https:\/\/app\.lapesquerapp\.es$/',
    ],


    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
