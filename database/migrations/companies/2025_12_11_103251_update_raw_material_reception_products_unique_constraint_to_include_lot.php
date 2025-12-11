<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Cambia el constraint único de ['reception_id', 'product_id'] 
     * a ['reception_id', 'product_id', 'lot'] para permitir múltiples líneas
     * del mismo producto con diferentes lotes en la misma recepción.
     */
    public function up(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        // Verificar si existe el constraint antiguo y eliminarlo
        $indexes = \DB::select("
            SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'raw_material_reception_products'
            AND NON_UNIQUE = 0
            AND INDEX_NAME != 'PRIMARY'
            GROUP BY INDEX_NAME
        ");
        
        $oldConstraintName = null;
        foreach ($indexes as $index) {
            $columns = explode(',', $index->columns);
            if (count($columns) === 2 && 
                in_array('reception_id', $columns) && 
                in_array('product_id', $columns) &&
                !in_array('lot', $columns)) {
                $oldConstraintName = $index->INDEX_NAME;
                break;
            }
        }

        // Eliminar constraint antiguo si existe
        if ($oldConstraintName) {
            // Usar SQL directo para eliminar el constraint por su nombre
            try {
                \DB::statement("ALTER TABLE raw_material_reception_products DROP INDEX `{$oldConstraintName}`");
            } catch (\Exception $e) {
                // Intentar con el método de Laravel
                try {
                    Schema::table('raw_material_reception_products', function (Blueprint $table) {
                        $table->dropUnique(['reception_id', 'product_id']);
                    });
                } catch (\Exception $e2) {
                    // Constraint no existe, continuar
                }
            }
        }

        // Verificar si ya existe el nuevo constraint
        $hasNewConstraint = false;
        foreach ($indexes as $index) {
            $columns = explode(',', $index->columns);
            if (count($columns) === 3 && 
                in_array('reception_id', $columns) && 
                in_array('product_id', $columns) &&
                in_array('lot', $columns)) {
                $hasNewConstraint = true;
                break;
            }
        }

        // Agregar nuevo constraint si no existe
        if (!$hasNewConstraint) {
            // Usar SQL directo con nombre corto para evitar error de nombre demasiado largo
            \DB::statement("
                ALTER TABLE raw_material_reception_products 
                ADD UNIQUE INDEX `rmrp_reception_product_lot_unique` (`reception_id`, `product_id`, `lot`)
            ");
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

        // Eliminar constraint nuevo
        try {
            \DB::statement("ALTER TABLE raw_material_reception_products DROP INDEX `rmrp_reception_product_lot_unique`");
        } catch (\Exception $e) {
            // Constraint no existe, continuar
        }

        // Restaurar constraint antiguo
        Schema::table('raw_material_reception_products', function (Blueprint $table) {
            // Limpiar duplicados antes de restaurar el constraint antiguo
            \DB::statement("
                DELETE r1 FROM raw_material_reception_products r1
                INNER JOIN raw_material_reception_products r2 
                WHERE r1.id > r2.id 
                AND r1.reception_id = r2.reception_id 
                AND r1.product_id = r2.product_id
            ");
            
            try {
                $table->unique(['reception_id', 'product_id']);
            } catch (\Exception $e) {
                // Constraint ya existe, continuar
            }
        });
    }
};
