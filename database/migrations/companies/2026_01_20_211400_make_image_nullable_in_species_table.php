<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Hace el campo image nullable en la tabla species ya que no se maneja
     * en el controlador y puede causar errores si es requerido.
     */
    public function up(): void
    {
        if (!Schema::hasTable('species')) {
            return;
        }

        Schema::table('species', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('species')) {
            return;
        }

        Schema::table('species', function (Blueprint $table) {
            // Nota: No podemos revertir a NOT NULL si hay valores NULL existentes
            // Solo cambiamos si no hay valores NULL
            $table->string('image')->nullable(false)->change();
        });
    }
};

