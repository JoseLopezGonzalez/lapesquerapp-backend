<?php

use Illuminate\Support\Facades\DB;

if (!function_exists('createTenantUser')) {
    function createTenantUser(string $database, string $name, string $email, string $password): void
    {
        DB::purge('tenant');
        config(['database.connections.tenant.database' => $database]);
        DB::reconnect('tenant');

        DB::connection('tenant')->table('users')->insert([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
