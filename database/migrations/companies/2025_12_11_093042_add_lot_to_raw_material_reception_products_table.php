<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega campo lot a raw_material_reception_products para almacenar el lote del producto.
     */
    public function up(): void
    {
        // Solo ejecutar en contexto tenant
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('raw_material_reception_products', function (Blueprint $table) {
            // Verificar si la columna ya existe antes de crearla
            if (!Schema::hasColumn('raw_material_reception_products', 'lot')) {
                $table->string('lot')->nullable()->after('product_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Solo ejecutar en contexto tenant
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('raw_material_reception_products', function (Blueprint $table) {
            // Verificar si la columna existe antes de eliminarla
            if (Schema::hasColumn('raw_material_reception_products', 'lot')) {
                $table->dropColumn('lot');
            }
        });
    }
};
