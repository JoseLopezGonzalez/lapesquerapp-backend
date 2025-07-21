<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
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
