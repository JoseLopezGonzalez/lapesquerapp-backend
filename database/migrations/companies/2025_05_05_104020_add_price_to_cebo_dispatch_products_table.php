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
        Schema::table('cebo_dispatch_products', function (Blueprint $table) {
            $table->decimal('price', 8, 3)->nullable()->after('net_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cebo_dispatch_products', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
