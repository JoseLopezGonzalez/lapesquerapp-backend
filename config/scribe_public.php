<?php

// Config "frontend" de Scribe: genera el subconjunto del contrato pensado para ser consumido
// por aplicaciones cliente (Next.js, futura app móvil). Excluye superadmin, impersonación,
// observabilidad interna y cualquier ruta de depuración.
//
// Se genera con: php artisan contract:publish (ver docs/api-contract.md).
// NO editar `config/scribe.php` para excluir estas rutas: ese archivo sigue siendo la
// config "interna/completa" usada por el equipo backend para introspección total de la API.
//
// Scribe es una dependencia de desarrollo; este archivo debe poder cargarse igualmente
// cuando el paquete no está instalado (p. ej. `config:cache` en producción, donde
// `composer install --no-dev` no incluye Scribe).
if (! class_exists(\Knuckles\Scribe\Config\Defaults::class)) {
    return [
        'title' => 'PesquerApp API (Frontend Contract)',
        'description' => '',
        'auth' => ['enabled' => false],
    ];
}

$base = require __DIR__.'/scribe.php';

$base['title'] = 'PesquerApp API — Contrato Frontend';
$base['description'] = 'Subconjunto de la API REST v2 de PesquerApp destinado a aplicaciones cliente '
    .'(Next.js, app móvil). No incluye rutas de superadmin, impersonación ni observabilidad interna.';

$base['routes'] = [
    [
        'match' => [
            'prefixes' => ['api/*'],
            'domains' => ['*'],
        ],
        'include' => [
            // 'users.index', 'POST /new', '/auth/*'
        ],
        'exclude' => [
            'GET /api/health',
            // Superadmin: gestión de tenants, impersonación, seguridad, migraciones, feature flags.
            'api/v2/superadmin/*',
            // Aprobación/rechazo de impersonación por URL firmada (sensible aunque no requiera auth).
            'api/v2/public/impersonation/*',
            // Observabilidad / infraestructura interna, si se añaden bajo estos prefijos en el futuro.
            'api/v2/debug/*',
            'api/v2/internal/*',
            'api/v2/system/*',
        ],
    ],
];

// Salida a una carpeta de trabajo (no servida directamente, no versionada). El comando
// `contract:publish` copia solo el `openapi.yaml` resultante a `public/openapi/frontend.yaml`.
$base['static']['output_path'] = 'storage/app/scribe-public';

// El Postman collection y la doc HTML interactiva no son necesarios para el contrato frontend;
// solo nos interesa el OpenAPI. Reduce tiempo de generación y tamaño de la salida de trabajo.
$base['postman']['enabled'] = false;
$base['try_it_out']['enabled'] = false;

return $base;
