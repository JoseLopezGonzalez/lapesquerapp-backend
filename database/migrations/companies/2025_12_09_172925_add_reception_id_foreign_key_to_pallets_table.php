<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega la foreign key para reception_id en pallets.
     * Esta migración se ejecuta después de que existan ambas tablas.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pallets') || !Schema::hasTable('raw_material_receptions')) {
            return;
        }

        // Verificar si la columna existe
        if (!Schema::hasColumn('pallets', 'reception_id')) {
            return;
        }

        // Verificar si la foreign key ya existe
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'pallets' 
            AND COLUMN_NAME = 'reception_id' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        if (empty($foreignKeys)) {
            // Crear la foreign key con onDelete('cascade')
            Schema::table('pallets', function (Blueprint $table) {
                $table->foreign('reception_id')
                    ->references('id')
                    ->on('raw_material_receptions')
                    ->onDelete('cascade');
            });
        }

        // Verificar si el índice existe
        $indexes = DB::select("
            SHOW INDEX FROM pallets 
            WHERE Key_name = 'pallets_reception_id_index'
        ");

        if (empty($indexes)) {
            // Agregar índice si no existe
            Schema::table('pallets', function (Blueprint $table) {
                $table->index('reception_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('pallets')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table) {
            // Eliminar la foreign key
            try {
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'pallets' 
                    AND COLUMN_NAME = 'reception_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                if (!empty($foreignKeys)) {
                    $constraintName = $foreignKeys[0]->CONSTRAINT_NAME;
                    $table->dropForeign([$constraintName]);
                }
            } catch (\Exception $e) {
                // La FK no existe, continuar
            }
            
            // Eliminar el índice
            try {
                $table->dropIndex(['reception_id']);
            } catch (\Exception $e) {
                // El índice no existe, continuar
            }
        });
    }
};
