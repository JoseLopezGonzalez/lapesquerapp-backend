<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Orden: de lo general a lo específico (guía Sail + arquitectura actual)
        $this->call(RoleSeeder::class);
        $this->call(UsersSeeder::class);
        $this->call(FAOZonesSeeder::class);
        $this->call(CalibersSeeder::class);
        $this->call(StoreOperatorUserSeeder::class);

        $companyConfig = config('company');

        $flattened = Arr::dot($companyConfig); // Convierte el array en clave.valor (OJO CON ESTO, Buscar ese company config y ponerlo en el seeder cuando se deje de usar)

        foreach ($flattened as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => "company.{$key}"],
                ['value' => $value]
            );
        }
    }
}
