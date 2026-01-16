<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PDF Generation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for PDF generation using Chromium/Chrome browser.
    |
    */

    'chromium' => [
        /*
        |--------------------------------------------------------------------------
        | Chromium/Chrome Binary Path
        |--------------------------------------------------------------------------
        |
        | The file path to the Chromium or Chrome executable.
        | Can be overridden via CHROMIUM_PATH environment variable.
        |
        | Common paths:
        | - Linux: /usr/bin/google-chrome or /usr/bin/chromium-browser
        | - Docker: /usr/bin/google-chrome or /usr/bin/chromium-browser
        | - macOS: /Applications/Google Chrome.app/Contents/MacOS/Google Chrome
        | - Windows: C:\Program Files\Google\Chrome\Application\chrome.exe
        |
        */
        'path' => env('CHROMIUM_PATH', '/usr/bin/google-chrome'),

        /*
        |--------------------------------------------------------------------------
        | Default Chromium Arguments
        |--------------------------------------------------------------------------
        |
        | Arguments passed to Chromium for PDF generation.
        | These are optimized for server environments and containers.
        |
        */
        'arguments' => [
            '--no-sandbox',
            '--disable-gpu',
            '--disable-translate',
            '--disable-extensions',
            '--disable-sync',
            '--disable-background-networking',
            '--disable-software-rasterizer',
            '--disable-default-apps',
            '--disable-dev-shm-usage',
            '--safebrowsing-disable-auto-update',
            '--run-all-compositor-stages-before-draw',
            '--no-first-run',
            '--print-to-pdf-no-header',
            '--no-pdf-header-footer',
            '--hide-scrollbars',
            '--ignore-certificate-errors',
        ],

        /*
        |--------------------------------------------------------------------------
        | PDF Margins
        |--------------------------------------------------------------------------
        |
        | Default margins for generated PDFs.
        | Values can be in mm, cm, in, or px.
        |
        */
        'margins' => [
            'top' => '10mm',
            'right' => '30mm',
            'bottom' => '10mm',
            'left' => '10mm',
        ],
    ],
];

