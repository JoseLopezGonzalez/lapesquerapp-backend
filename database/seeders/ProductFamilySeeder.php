<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductFamily;
use App\Models\ProductCategory;

/**
 * Familias de producto de desarrollo (Fresco entero, Congelado eviscerado, etc.).
 * Origen: nomenclatura sector.
 * Depende de: ProductCategorySeeder.
 */
class ProductFamilySeeder extends Seeder
{
    public function run(): void
    {
        $frescoCategory = ProductCategory::where('name', 'Fresco')->first();
        $congeladoCategory = ProductCategory::where('name', 'Congelado')->first();

        if (! $frescoCategory || ! $congeladoCategory) {
            $this->command->warn('ProductFamilySeeder: Ejecuta antes ProductCategorySeeder.');
            return;
        }

        $families = [
            // Familias de productos frescos
            [
                'name' => 'Fresco entero',
                'description' => 'Productos frescos enteros sin procesar',
                'category_id' => $frescoCategory->id,
                'active' => true,
            ],
            [
                'name' => 'Fresco eviscerado',
                'description' => 'Productos frescos eviscerados',
                'category_id' => $frescoCategory->id,
                'active' => true,
            ],
            [
                'name' => 'Fresco fileteado',
                'description' => 'Productos frescos fileteados',
                'category_id' => $frescoCategory->id,
                'active' => true,
            ],
            
            // Familias de productos congelados
            [
                'name' => 'Congelado entero',
                'description' => 'Productos congelados enteros',
                'category_id' => $congeladoCategory->id,
                'active' => true,
            ],
            [
                'name' => 'Congelado eviscerado',
                'description' => 'Productos congelados eviscerados',
                'category_id' => $congeladoCategory->id,
                'active' => true,
            ],
            [
                'name' => 'Congelado fileteado',
                'description' => 'Productos congelados fileteados',
                'category_id' => $congeladoCategory->id,
                'active' => true,
            ],
            [
                'name' => 'Elaborado congelado',
                'description' => 'Productos elaborados y congelados',
                'category_id' => $congeladoCategory->id,
                'active' => true,
            ],
            [
                'name' => 'Elaborado en bandeja',
                'description' => 'Productos elaborados y presentados en bandeja',
                'category_id' => $congeladoCategory->id,
                'active' => true,
            ],
        ];

        foreach ($families as $family) {
            ProductFamily::firstOrCreate(
                ['name' => $family['name']],
                $family
            );
        }
    }
}
