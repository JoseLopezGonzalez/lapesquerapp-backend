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
            $table->string('article_gtin')->nullable()->change();
            $table->string('box_gtin')->nullable()->change();
            $table->string('pallet_gtin')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('article_gtin')->nullable(false)->change();
            $table->string('box_gtin')->nullable(false)->change();
            $table->string('pallet_gtin')->nullable(false)->change();
        });
    }
};
