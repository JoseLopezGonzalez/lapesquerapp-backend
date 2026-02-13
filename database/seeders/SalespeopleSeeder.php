<?php

namespace Database\Seeders;

use App\Models\Salesperson;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Comerciales / vendedores de desarrollo — entorno tipo producción.
 * Inspirado en patrones reales: name (nombre o nombre completo), emails (varios con ";").
 * Solo añade los que no existan (firstOrCreate por nombre).
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
    }
}
