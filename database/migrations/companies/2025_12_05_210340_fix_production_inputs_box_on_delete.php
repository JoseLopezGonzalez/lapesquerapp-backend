<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Cambia onDelete('cascade') a onDelete('restrict') en box_id
     * para impedir eliminar cajas que están siendo usadas en producción.
     * La caja debe mantenerse para trazabilidad incluso si se elimina el input.
     */
    public function up(): void
    {
        if (!Schema::hasTable('production_inputs')) {
            return;
        }

        Schema::table('production_inputs', function (Blueprint $table) {
            // Eliminar la foreign key existente si existe
            try {
                $table->dropForeign(['box_id']);
            } catch (\Exception $e) {
                // La FK no existe, continuar
            }
        });
        
        // Recrear con onDelete('restrict')
        Schema::table('production_inputs', function (Blueprint $table) {
            $table->foreign('box_id')
                ->references('id')
                ->on('boxes')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_inputs', function (Blueprint $table) {
            // Eliminar la foreign key
            $table->dropForeign(['box_id']);
            
            // Recrear con onDelete('cascade') (comportamiento original)
            $table->foreign('box_id')
                ->references('id')
                ->on('boxes')
                ->onDelete('cascade');
        });
    }
};
