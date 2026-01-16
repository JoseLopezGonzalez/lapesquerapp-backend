<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Export Memory and Time Limits
    |--------------------------------------------------------------------------
    |
    | These settings control the memory and execution time limits for Excel
    | and PDF exports. They can be overridden per export type if needed.
    |
    */

    'limits' => [
        // Standard exports (single order, basic reports)
        'standard' => [
            'memory_limit' => '1024M',
            'max_execution_time' => 300, // seconds
        ],

        // Large exports (multiple orders with filters, complex reports)
        'large' => [
            'memory_limit' => '2048M',
            'max_execution_time' => 600, // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Type Configurations
    |--------------------------------------------------------------------------
    |
    | Specific configurations for different export types. If not specified,
    | the 'standard' limits will be used.
    |
    */

    'types' => [
        'boxes_report' => 'large',
        'raw_material_reception_a3erp' => 'large',
        'raw_material_reception_facilcom' => 'large',
        'cebo_dispatch_a3erp' => 'large',
        'cebo_dispatch_a3erp2' => 'large',
        'cebo_dispatch_facilcom' => 'large',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource-Intensive Operations
    |--------------------------------------------------------------------------
    |
    | These settings control memory and execution time limits for
    | resource-intensive operations that are not exports but still
    | require higher limits (statistics, store details, etc.).
    |
    */

    'operations' => [
        // Statistics operations (order statistics, rankings)
        'statistics' => [
            'memory_limit' => '512M',
            'max_execution_time' => 300, // seconds
        ],

        // Store operations (store details with many pallets)
        'store' => [
            'memory_limit' => '2048M',
            'max_execution_time' => 300, // seconds
        ],

        // Report operations (order reports, exports)
        'reports' => [
            'memory_limit' => '1024M',
            'max_execution_time' => 300, // seconds
        ],
    ],
];

