<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        // Limpiar duplicados antes de agregar el constraint único
        // Mantener solo el registro con el ID más bajo para cada combinación
        \DB::statement("
            DELETE r1 FROM raw_material_reception_products r1
            INNER JOIN raw_material_reception_products r2 
            WHERE r1.id > r2.id 
            AND r1.reception_id = r2.reception_id 
            AND r1.product_id = r2.product_id
        ");

        // Verificar si ya existe el constraint único usando SQL
        $indexes = \DB::select("
            SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'raw_material_reception_products'
            AND NON_UNIQUE = 0
            AND INDEX_NAME != 'PRIMARY'
            GROUP BY INDEX_NAME
        ");
        
        $hasCompositeUnique = false;
        foreach ($indexes as $index) {
            $columns = explode(',', $index->columns);
            if (count($columns) === 2 && 
                in_array('reception_id', $columns) && 
                in_array('product_id', $columns)) {
                $hasCompositeUnique = true;
                break;
            }
        }

        if (!$hasCompositeUnique) {
            Schema::table('raw_material_reception_products', function (Blueprint $table) {
                $table->unique(['reception_id', 'product_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('raw_material_reception_products', function (Blueprint $table) {
            try {
                $table->dropUnique(['reception_id', 'product_id']);
            } catch (\Exception $e) {
                // Constraint does not exist, continue
            }
        });
    }
};
