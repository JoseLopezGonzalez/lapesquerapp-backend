<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\ExternalUser;
use App\Models\Salesperson;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Siembra usuarios en roles límite para QA de permisos y visibilidad.
 * Uso: dataset edge únicamente. Nombres claramente identificables.
 */
class TenantPermissionsScenariosSeeder extends Seeder
{
    public function run(): void
    {
        // Comercial con usuario propio (para probar que solo ve sus prospectos/rutas)
        $comercialUser = User::firstOrCreate(
            ['email' => 'edge.comercial@pesquerapp.test'],
            [
                'name'   => 'Edge Comercial Permisos',
                'role'   => Role::Comercial->value,
                'active' => true,
            ]
        );

        Salesperson::firstOrCreate(
            ['email' => 'edge.comercial@pesquerapp.test'],
            [
                'name'    => 'Edge Comercial Permisos',
                'user_id' => $comercialUser->id,
                'active'  => true,
            ]
        );

        // Comercial SIN usuario vinculado (Salesperson huérfano)
        Salesperson::firstOrCreate(
            ['email' => 'edge.comercial.orphan@pesquerapp.test'],
            [
                'name'    => 'Edge Comercial Huérfano',
                'user_id' => null,
                'active'  => true,
            ]
        );

        // Operario (acceso limitado a su almacén)
        User::firstOrCreate(
            ['email' => 'edge.operario@pesquerapp.test'],
            [
                'name'   => 'Edge Operario Almacén',
                'role'   => Role::Operario->value,
                'active' => true,
            ]
        );

        // Repartidor / Autoventa (acceso solo a rutas propias)
        User::firstOrCreate(
            ['email' => 'edge.repartidor@pesquerapp.test'],
            [
                'name'   => 'Edge Repartidor Autoventa',
                'role'   => Role::RepartidorAutoventa->value,
                'active' => true,
            ]
        );

        // Usuario de Dirección (solo lectura en ciertos módulos)
        User::firstOrCreate(
            ['email' => 'edge.direccion@pesquerapp.test'],
            [
                'name'   => 'Edge Dirección Lectura',
                'role'   => Role::Direccion->value,
                'active' => true,
            ]
        );

        // Usuario inactivo (no debe poder autenticarse ni ver datos)
        User::firstOrCreate(
            ['email' => 'edge.inactive@pesquerapp.test'],
            [
                'name'   => 'Edge Usuario Inactivo',
                'role'   => Role::Comercial->value,
                'active' => false,
            ]
        );

        // External user activo (acceso a tienda/punto externo)
        ExternalUser::firstOrCreate(
            ['email' => 'edge.external.active@pesquerapp.test'],
            [
                'name'         => 'Edge External Activo',
                'company_name' => 'Tienda Edge Activa',
                'type'         => ExternalUser::TYPE_MAQUILADOR,
                'is_active'    => true,
            ]
        );

        // External user inactivo (no debe poder acceder)
        ExternalUser::firstOrCreate(
            ['email' => 'edge.external.inactive@pesquerapp.test'],
            [
                'name'         => 'Edge External Inactivo',
                'company_name' => 'Tienda Edge Inactiva',
                'type'         => ExternalUser::TYPE_MAQUILADOR,
                'is_active'    => false,
            ]
        );
    }
}
