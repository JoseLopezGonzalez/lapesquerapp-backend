<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Renombra la columna state_id a status para evitar que Laravel
     * intente resolver automáticamente relaciones belongsTo
     */
    public function up(): void
    {
        if (!Schema::hasTable('pallets')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table) {
            $table->renameColumn('state_id', 'status');
        });
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
            $table->renameColumn('status', 'state_id');
        });
    }
};
