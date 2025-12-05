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
            AND TABLE_NAME = 'articles' 
            AND COLUMN_NAME = 'category_id' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Drop existing foreign keys using raw SQL
        foreach ($foreignKeys as $fk) {
            try {
                \DB::statement("ALTER TABLE articles DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // FK does not exist or already dropped, continue
            }
        }

        Schema::table('articles', function (Blueprint $table) {
            // category_id: restrict (no eliminar categorías con artículos)
            $table->foreign('category_id')
                ->references('id')
                ->on('article_categories')
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

        Schema::table('articles', function (Blueprint $table) {
            try {
                $table->dropForeign(['category_id']);
            } catch (\Exception $e) {
                // FK does not exist, continue
            }
        });
    }
};
