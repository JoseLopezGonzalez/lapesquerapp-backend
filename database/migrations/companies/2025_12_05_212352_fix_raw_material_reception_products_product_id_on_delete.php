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
        if (config('database.default') !== 'tenant') {
            return;
        }

        // Get existing foreign key names
        $foreignKeys = \DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'raw_material_reception_products' 
            AND COLUMN_NAME = 'product_id' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Drop existing foreign keys using raw SQL
        foreach ($foreignKeys as $fk) {
            try {
                \DB::statement("ALTER TABLE raw_material_reception_products DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // FK does not exist or already dropped, continue
            }
        }

        Schema::table('raw_material_reception_products', function (Blueprint $table) {
            // product_id: cambiar de cascade a restrict (no eliminar productos que están en recepciones)
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
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('raw_material_reception_products', function (Blueprint $table) {
            try {
                $table->dropForeign(['product_id']);
            } catch (\Exception $e) {
                // FK does not exist, continue
            }
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }
};
