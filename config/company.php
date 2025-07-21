<?php
//config/company.php
/* Constantes de configuración de la empresa */

return [

    // Identidad de empresa
    'name' => 'Congelados Brisamar S.L.',
    'cif' => 'B21573282',
    'sanitary_number' => 'ES 12.021462/H CE',

    // Dirección fiscal / operativa
    'address' => [
        'street' => 'C/Dieciocho de Julio de 1922 Nº2',
        'postal_code' => '21410',
        'city' => 'Isla Cristina',
        'province' => 'Huelva',
        'country' => 'España',
    ],

    // Web y branding
    'website_url' => 'https://congeladosbrisamar.es',
    'logo_url_small' => 'https://congeladosbrisamar.es/logos/logo-brisamar-small.png',

    // Lugar de carga y firma (usado en CMR, PDF, etc.)
    'loading_place' => 'Isla Cristina - Huelva',
    'signature_location' => 'Isla Cristina',

    // Correos ocultos globales para BCC en envíos
    'bcc_email' => 'pedidos@congeladosbrisamar.es',

    // Información de contacto
    'contact' => [

        // Operaciones y pedidos
        'email_operations' => 'jose@congeladosbrisamar.es',
        'email_orders' => 'pedidos@congeladosbrisamar.es',
        'phone_orders' => '+34 613 091 494',

        // Administración
        'email_admin' => 'administracion@congeladosbrisamar.es',
        'phone_admin' => '+34 613 09 14 94',

        // Contactos específicos
        'emergency_email' => 'emergencias@congeladosbrisamar.es',
        'incidents_email' => 'incidencias@congeladosbrisamar.es',
        'loading_email' => 'carga@congeladosbrisamar.es',
        'unloading_email' => 'descarga@congeladosbrisamar.es',
    ],

    // Información legal (enlaces PDF)
    'legal' => [
        'terms_url' => '/docs/condiciones-legales.pdf',
        'privacy_policy_url' => '/docs/politica-privacidad.pdf',
    ],
];
