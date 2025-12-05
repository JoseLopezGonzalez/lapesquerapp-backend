<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Cambia onDelete('cascade') a onDelete('set null') en parent_record_id
     * para que los hijos se conviertan en raíz cuando se elimina el padre,
     * manteniendo la trazabilidad.
     */
    public function up(): void
    {
        if (!Schema::hasTable('production_records')) {
            return;
        }

        Schema::table('production_records', function (Blueprint $table) {
            // Eliminar la foreign key existente si existe
            try {
                $table->dropForeign(['parent_record_id']);
            } catch (\Exception $e) {
                // La FK no existe, continuar
            }
        });
        
        // Recrear con onDelete('set null')
        Schema::table('production_records', function (Blueprint $table) {
            $table->foreign('parent_record_id')
                ->references('id')
                ->on('production_records')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_records', function (Blueprint $table) {
            // Eliminar la foreign key
            $table->dropForeign(['parent_record_id']);
            
            // Recrear con onDelete('cascade') (comportamiento original)
            $table->foreign('parent_record_id')
                ->references('id')
                ->on('production_records')
                ->onDelete('cascade');
        });
    }
};
