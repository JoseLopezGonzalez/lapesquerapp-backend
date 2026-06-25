<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de almacenamiento
    |--------------------------------------------------------------------------
    | Clave del disco de Laravel Storage que se usa para guardar adjuntos.
    | En producción se puede mapear a S3/R2 cambiando ATTACHMENTS_DISK_DRIVER.
    */
    'disk' => env('ATTACHMENTS_DISK', 'attachments'),

    /*
    |--------------------------------------------------------------------------
    | Colecciones por entidad (morphMap key => colecciones)
    |--------------------------------------------------------------------------
    | Cada colección define:
    |   mimes     → lista blanca de MIME detectados server-side.
    |   max_size  → tamaño máximo en bytes.
    |   max_count → máximo de adjuntos por entidad+colección.
    */
    'collections' => [

        'pallet' => [
            'pallet_image' => [
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 10 * 1024 * 1024, // 10 MB
                'max_count' => 20,
            ],
        ],

        'order' => [
            'order_document' => [
                'mimes' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
                'max_size' => 20 * 1024 * 1024, // 20 MB
                'max_count' => 50,
            ],
            'order_image' => [
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 10 * 1024 * 1024, // 10 MB
                'max_count' => 20,
            ],
        ],

        // 'reception' => [...],   // se define en Fase 4

    ],

];
