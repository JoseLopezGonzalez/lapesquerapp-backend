<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

if (!function_exists('createTenantUser')) {
    function createTenantUser(string $database, string $name, string $email, string $password, ?string $roleName = null): void
    {
        DB::purge('tenant');
        config(['database.connections.tenant.database' => $database]);
        DB::reconnect('tenant');

        // Crear el usuario desde el modelo Eloquent
        $user = new User();
        $user->setConnection('tenant');
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->save();

        // Asignar rol si se indica
        if ($roleName) {
            $user->assignRole($roleName);
        }
    }
}
