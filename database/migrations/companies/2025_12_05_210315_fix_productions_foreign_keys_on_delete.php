<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Cambia onDelete('cascade') a onDelete('restrict') en species_id y capture_zone_id
     * para proteger la trazabilidad histórica de las producciones.
     */
    public function up(): void
    {
        if (!Schema::hasTable('productions')) {
            return;
        }

        Schema::table('productions', function (Blueprint $table) {
            // Eliminar las foreign keys existentes si existen
            try {
                $table->dropForeign(['species_id']);
            } catch (\Exception $e) {
                // La FK no existe, continuar
            }
            
            try {
                $table->dropForeign(['capture_zone_id']);
            } catch (\Exception $e) {
                // La FK no existe, continuar
            }
        });
        
        // Recrear con onDelete('restrict')
        Schema::table('productions', function (Blueprint $table) {
            $table->foreign('species_id')
                ->references('id')
                ->on('species')
                ->onDelete('restrict');
                
            $table->foreign('capture_zone_id')
                ->references('id')
                ->on('capture_zones')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            // Eliminar las foreign keys
            $table->dropForeign(['species_id']);
            $table->dropForeign(['capture_zone_id']);
            
            // Recrear con onDelete('cascade') (comportamiento original)
            $table->foreign('species_id')
                ->references('id')
                ->on('species')
                ->onDelete('cascade');
                
            $table->foreign('capture_zone_id')
                ->references('id')
                ->on('capture_zones')
                ->onDelete('cascade');
        });
    }
};
