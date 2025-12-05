<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Cambia onDelete('cascade') a onDelete('restrict') en product_id
     * para impedir eliminar productos que son catálogos maestros.
     * Los productos no deben eliminarse cuando se elimina un output.
     */
    public function up(): void
    {
        if (!Schema::hasTable('production_outputs')) {
            return;
        }

        Schema::table('production_outputs', function (Blueprint $table) {
            // Eliminar la foreign key existente si existe
            try {
                $table->dropForeign(['product_id']);
            } catch (\Exception $e) {
                // La FK no existe, continuar
            }
        });
        
        // Recrear con onDelete('restrict')
        Schema::table('production_outputs', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_outputs', function (Blueprint $table) {
            // Eliminar la foreign key
            $table->dropForeign(['product_id']);
            
            // Recrear con onDelete('cascade') (comportamiento original)
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }
};
