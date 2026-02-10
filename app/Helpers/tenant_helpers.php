<?php

use App\Enums\Role;
use Illuminate\Support\Facades\DB;
use App\Models\User;

if (!function_exists('createTenantUser')) {
    function createTenantUser(string $database, string $name, string $email, ?string $roleName = null): void
    {
        DB::purge('tenant');
        config(['database.connections.tenant.database' => $database]);
        DB::reconnect('tenant');

        $user = new User();
        $user->setConnection('tenant');
        $user->name = $name;
        $user->email = $email;
        $user->role = ($roleName && in_array($roleName, Role::values(), true)) ? $roleName : Role::Operario->value;
        $user->save();
    }
}
