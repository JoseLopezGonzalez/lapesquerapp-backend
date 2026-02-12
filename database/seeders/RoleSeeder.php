<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Los roles están en la columna users.role (App\Enums\Role), no en una tabla roles.
     * La migración 2026_02_10_120000_migrate_roles_to_enum_on_users eliminó las tablas roles y role_user.
     * Este seeder se mantiene en TenantDatabaseSeeder por compatibilidad; no hace nada.
     */
    public function run(): void
    {
        // No-op: roles en users.role (enum), no hay tabla roles
    }
}
