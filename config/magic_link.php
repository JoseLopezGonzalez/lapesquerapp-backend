<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Magic Link & OTP expiration (minutes)
    |--------------------------------------------------------------------------
    */
    'expires_minutes' => (int) env('MAGIC_LINK_EXPIRES_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Cleanup: delete expired tokens
    |--------------------------------------------------------------------------
    | When running auth:cleanup-magic-tokens, records with expires_at < now()
    | are always deleted.
    */
    'cleanup' => [
        'delete_expired' => true,

        /*
        | Delete used tokens older than this (days). Set to 0 to keep all used.
        */
        'used_older_than_days' => (int) env('MAGIC_LINK_CLEANUP_USED_DAYS', 1),
    ],
];
