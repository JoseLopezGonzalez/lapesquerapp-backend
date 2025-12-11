<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega reception_id a pallets para vincular palets con recepciones de materia prima.
     * onDelete('cascade') elimina los palets si se elimina la recepción.
     */
    public function up(): void
    {
        // Solo ejecutar en contexto tenant
        if (config('database.default') !== 'tenant') {
            return;
        }

        if (!Schema::hasTable('pallets')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table) {
            // Verificar si la columna existe, si no, crearla
            if (!Schema::hasColumn('pallets', 'reception_id')) {
                $table->unsignedBigInteger('reception_id')->nullable()->after('order_id');
            }
        });
        
        // Eliminar FK existente si existe (puede tener nombre diferente)
        try {
            Schema::table('pallets', function (Blueprint $table) {
                $table->dropForeign(['reception_id']);
            });
        } catch (\Exception $e) {
            // La FK no existe o tiene otro nombre, continuar
        }
        
        // Verificar que la tabla raw_material_receptions existe antes de crear la FK
        if (Schema::hasTable('raw_material_receptions')) {
            // Crear la foreign key con onDelete('cascade')
            Schema::table('pallets', function (Blueprint $table) {
                $table->foreign('reception_id')
                    ->references('id')
                    ->on('raw_material_receptions')
                    ->onDelete('cascade');
            });
            
            // Agregar índice para mejorar rendimiento
            Schema::table('pallets', function (Blueprint $table) {
                if (!$this->hasIndex('pallets', 'pallets_reception_id_index')) {
                    $table->index('reception_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Solo ejecutar en contexto tenant
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('pallets', function (Blueprint $table) {
            // Eliminar la foreign key
            try {
                $table->dropForeign(['reception_id']);
            } catch (\Exception $e) {
                // La FK no existe, continuar
            }
            
            // Eliminar el índice
            try {
                $table->dropIndex(['reception_id']);
            } catch (\Exception $e) {
                // El índice no existe, continuar
            }
            
            // Eliminar la columna
            if (Schema::hasColumn('pallets', 'reception_id')) {
                $table->dropColumn('reception_id');
            }
        });
    }

    /**
     * Verificar si existe un índice en la tabla
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $indexes = $connection->select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );
        
        return count($indexes) > 0;
    }
};
