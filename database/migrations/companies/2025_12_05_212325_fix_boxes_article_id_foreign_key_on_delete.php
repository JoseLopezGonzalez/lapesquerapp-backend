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

        // Fix invalid datetime values before adding foreign key
        \DB::statement("UPDATE boxes SET created_at = NOW() WHERE created_at = '0000-00-00 00:00:00' OR created_at IS NULL");
        \DB::statement("UPDATE boxes SET updated_at = NOW() WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL");

        // Get the actual foreign key name using SQL
        $foreignKeys = \DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'boxes' 
            AND COLUMN_NAME = 'article_id' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Drop existing foreign keys using raw SQL
        foreach ($foreignKeys as $fk) {
            try {
                \DB::statement("ALTER TABLE boxes DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // FK does not exist or already dropped, continue
            }
        }

        // Add new foreign key with restrict
        Schema::table('boxes', function (Blueprint $table) {
            $table->foreign('article_id')
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

        Schema::table('boxes', function (Blueprint $table) {
            $table->dropForeign(['article_id']);
            $table->foreign('article_id')
                ->references('id')
                ->on('products');
        });
    }
};
