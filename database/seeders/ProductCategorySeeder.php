<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

/**
 * Categorías de producto de desarrollo (Fresco, Congelado).
 * Depende de: ninguno.
 */
class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Fresco',
                'description' => 'Productos frescos sin procesar',
                'active' => true,
            ],
            [
                'name' => 'Congelado',
                'description' => 'Productos congelados',
                'active' => true,
            ],
        ];

        foreach ($categories as $category) {
            ProductCategory::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
