<?php

return [
    'frontend_url' => env('SUPERADMIN_FRONTEND_URL', 'https://admin.lapesquerapp.es'),

    // URL base de los tenants. El subdomain se antepone automáticamente.
    // Ejemplo: "brisamar" → https://brisamar.lapesquerapp.es
    'tenant_base_url' => env('TENANT_BASE_URL', 'https://lapesquerapp.es'),
];
