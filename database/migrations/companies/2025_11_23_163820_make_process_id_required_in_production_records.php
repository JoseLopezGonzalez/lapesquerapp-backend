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
        Schema::table('production_records', function (Blueprint $table) {
            // Primero eliminar la foreign key existente
            $table->dropForeign(['process_id']);
        });
        
        // Cambiar process_id a NOT NULL (fuera del Schema::table para evitar problemas)
        Schema::table('production_records', function (Blueprint $table) {
            $table->foreignId('process_id')->nullable(false)->change();
        });
        
        // Recrear la foreign key sin onDelete('set null') ya que ahora es required
        Schema::table('production_records', function (Blueprint $table) {
            $table->foreign('process_id')->references('id')->on('processes')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_records', function (Blueprint $table) {
            // Eliminar la foreign key
            $table->dropForeign(['process_id']);
            
            // Volver a hacer process_id nullable
            $table->foreignId('process_id')->nullable()->change();
            
            // Recrear la foreign key original
            $table->foreign('process_id')->references('id')->on('processes')->onDelete('set null');
        });
    }
};
