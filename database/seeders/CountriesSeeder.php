<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Países (menú Clientes). Datos de desarrollo.
 * Depende de: ninguno.
 */
class CountriesSeeder extends Seeder
{
    public function run(): void
    {
        $countries = ['España', 'Portugal', 'Francia', 'Marruecos', 'Italia'];

        foreach ($countries as $name) {
            Country::firstOrCreate(['name' => $name]);
        }
    }
}
