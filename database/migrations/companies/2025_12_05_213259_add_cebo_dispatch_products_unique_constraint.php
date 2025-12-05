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
            DELETE d1 FROM cebo_dispatch_products d1
            INNER JOIN cebo_dispatch_products d2 
            WHERE d1.id > d2.id 
            AND d1.dispatch_id = d2.dispatch_id 
            AND d1.product_id = d2.product_id
        ");

        // Verificar si ya existe el constraint único usando SQL
        $indexes = \DB::select("
            SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'cebo_dispatch_products'
            AND NON_UNIQUE = 0
            AND INDEX_NAME != 'PRIMARY'
            GROUP BY INDEX_NAME
        ");
        
        $hasCompositeUnique = false;
        foreach ($indexes as $index) {
            $columns = explode(',', $index->columns);
            if (count($columns) === 2 && 
                in_array('dispatch_id', $columns) && 
                in_array('product_id', $columns)) {
                $hasCompositeUnique = true;
                break;
            }
        }

        if (!$hasCompositeUnique) {
            Schema::table('cebo_dispatch_products', function (Blueprint $table) {
                $table->unique(['dispatch_id', 'product_id']);
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

        Schema::table('cebo_dispatch_products', function (Blueprint $table) {
            try {
                $table->dropUnique(['dispatch_id', 'product_id']);
            } catch (\Exception $e) {
                // Constraint does not exist, continue
            }
        });
    }
};
