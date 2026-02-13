<?php

namespace Database\Seeders;

use App\Models\Transport;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Transportes de desarrollo — entorno tipo producción.
 * Inspirado en patrones reales: name (empresa S.L./S.L.U.), vat_number (B + 8 dígitos),
 * address (multilínea), emails (cadena con ";").
 * Solo añade los que no existan (firstOrCreate por nombre).
 */
class TransportsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');
        $faker->seed(5300);

        $companyNames = [
            'Transportes Marítimos del Sur S.L.',
            'Logística Costa S.L.U.',
            'Frío Express Andalucía S.L.',
            'Carga y Distribución S.L.U.',
            'Transmediterráneo S.L.',
        ];

        foreach ($companyNames as $name) {
            $address = implode("\n", [
                $faker->streetAddress(),
                $faker->postcode() . ' ' . $faker->city() . ' (' . $faker->randomElement(['Huelva', 'Sevilla', 'Cádiz', 'Málaga', 'Córdoba', 'Granada', 'Almería', 'Valencia', 'A Coruña']) . ')',
            ]);
            $emails = $faker->unique()->companyEmail() . ';';

            Transport::firstOrCreate(
                ['name' => $name],
                [
                    'vat_number' => 'B' . $faker->unique()->numerify('########'),
                    'address' => $address,
                    'emails' => $emails,
                ]
            );
        }
    }
}
