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
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('id');
            $table->unsignedBigInteger('family_id')->nullable()->after('category_id');
            
            $table->foreign('category_id')->references('id')->on('product_categories')->onDelete('set null');
            $table->foreign('family_id')->references('id')->on('product_families')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['family_id']);
            $table->dropColumn(['category_id', 'family_id']);
        });
    }
};
