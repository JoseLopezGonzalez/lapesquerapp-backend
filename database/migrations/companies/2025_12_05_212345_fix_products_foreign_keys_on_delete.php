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
            AND TABLE_NAME = 'products' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Drop existing foreign keys using raw SQL
        foreach ($foreignKeys as $fk) {
            try {
                \DB::statement("ALTER TABLE products DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // FK does not exist or already dropped, continue
            }
        }

        Schema::table('products', function (Blueprint $table) {
            // species_id: restrict (no eliminar especies con productos)
            $table->foreign('species_id')
                ->references('id')
                ->on('species')
                ->onDelete('restrict');

            // capture_zone_id: restrict (no eliminar zonas con productos)
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
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $foreignKeys = ['species_id', 'capture_zone_id'];
            foreach ($foreignKeys as $fk) {
                try {
                    $table->dropForeign([$fk]);
                } catch (\Exception $e) {
                    // FK does not exist, continue
                }
            }
        });
    }
};
