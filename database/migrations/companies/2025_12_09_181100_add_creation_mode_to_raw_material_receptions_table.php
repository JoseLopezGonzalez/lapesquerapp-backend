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
        Schema::table('raw_material_receptions', function (Blueprint $table) {
            if (!Schema::hasColumn('raw_material_receptions', 'creation_mode')) {
                $table->string('creation_mode', 20)->nullable()->after('notes')->comment('Modo de creación: "lines" (por líneas) o "pallets" (por palets)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_material_receptions', function (Blueprint $table) {
            if (Schema::hasColumn('raw_material_receptions', 'creation_mode')) {
                $table->dropColumn('creation_mode');
            }
        });
    }
};
