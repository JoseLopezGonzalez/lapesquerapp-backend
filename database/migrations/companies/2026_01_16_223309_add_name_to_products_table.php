<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Agregar columna name si no existe
            if (!Schema::hasColumn('products', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
        });

        // Migrar datos de articles.name a products.name
        // Usar DB::raw para evitar problemas con el tenant connection
        DB::statement('
            UPDATE products p
            INNER JOIN articles a ON p.id = a.id
            SET p.name = a.name
            WHERE p.name IS NULL OR p.name = ""
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
