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


    // Permitir varios orígenes (especifica los dominios)
    'allowed_origins' => [
        'http://localhost:3000', // Origen local (por ejemplo, frontend en desarrollo)
        'http://localhost:5173',
        'https://lapesquerapp.es',
        'https://brisamar.lapesquerapp.es',
        'https://test.lapesquerapp.es',
        'https://pymcolorao.lapesquerapp.es',
        'http://brisamar.localhost:3000',
        'http://test.localhost:3000',
        'http://pymcolorao.localhost:3000',
    ],


    'allowed_origins_patterns' => [
        '/^https:\/\/[a-z0-9\-]+\.lapesquerapp\.es$/',
        '/^https:\/\/[a-z0-9\-]+\.congeladosbrisamar\.es$/',
        '/^http:\/\/[a-z0-9\-]+\.localhost(?::\d+)?$/',
        '/^http:\/\/127\.0\.0\.1(?::\d+)?$/',
    ],


    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
