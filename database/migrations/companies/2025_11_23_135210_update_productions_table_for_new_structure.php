<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NOTA: Mantenemos los campos existentes (diagram_data, capture_zone_id, date)
     * para facilitar la migración gradual en el frontend.
     * Solo agregamos los nuevos campos necesarios para la nueva estructura.
     */
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            // Hacer species_id nullable (opcional según especificación)
            $table->foreignId('species_id')->nullable()->change();
            
            // Agregar nuevos campos de timestamps para la nueva estructura
            $table->timestamp('opened_at')->nullable()->after('notes');
            $table->timestamp('closed_at')->nullable()->after('opened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            // Eliminar nuevos campos
            $table->dropColumn(['opened_at', 'closed_at']);
            
            // Restaurar species_id como required (si era required antes)
            // Nota: Verificar si originalmente era nullable o no
        });
    }
};
