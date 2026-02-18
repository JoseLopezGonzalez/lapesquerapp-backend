<?php

namespace Database\Seeders;

use App\Models\Salesperson;
use App\Models\User;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Comerciales / vendedores de desarrollo — entorno tipo producción.
 * Inspirado en patrones reales: name (nombre o nombre completo), emails (varios con ";").
 * Solo añade los que no existan (firstOrCreate por nombre).
 * Vincula un User con rol comercial al primer Salesperson sin user_id (para pruebas de permisos).
 */
class SalespeopleSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');
        $faker->seed(5200);

        $names = [
            'Vicente',
            'María',
            'Carlos García',
            'Ana',
            'Luis Fernández',
            'Elena',
        ];

        foreach ($names as $name) {
            $emails = $faker->unique()->safeEmail() . ';';
            if ($faker->boolean(30)) {
                $emails .= ' CC:' . $faker->safeEmail() . ';';
            }

            Salesperson::firstOrCreate(
                ['name' => $name],
                ['emails' => $emails]
            );
        }

        // Vincular un usuario con rol comercial a un comercial (para pruebas de scoping por salesperson)
        $comercialUser = User::where('role', Role::Comercial->value)->first();
        if ($comercialUser) {
            // Verificar que el usuario no esté ya asignado a otro salesperson
            $existingSalesperson = Salesperson::where('user_id', $comercialUser->id)->first();
            if (! $existingSalesperson) {
                $salespersonWithoutUser = Salesperson::whereNull('user_id')->first();
                if ($salespersonWithoutUser) {
                    $salespersonWithoutUser->update(['user_id' => $comercialUser->id]);
                }
            }
        }
    }
}
