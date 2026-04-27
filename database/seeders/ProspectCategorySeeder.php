<?php

namespace Database\Seeders;

use App\Models\ProspectCategory;
use Illuminate\Database\Seeder;

class ProspectCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Restaurante', 'description' => 'Restaurantes y grupos de restauración', 'active' => true],
            ['name' => 'Mayorista', 'description' => 'Compradores mayoristas', 'active' => true],
            ['name' => 'Distribuidor', 'description' => 'Distribuidores comerciales', 'active' => true],
            ['name' => 'Gran empresa', 'description' => 'Cuentas corporativas de mayor tamaño', 'active' => true],
        ];

        foreach ($categories as $category) {
            ProspectCategory::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
