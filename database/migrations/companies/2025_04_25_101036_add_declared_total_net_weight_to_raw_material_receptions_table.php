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
            $table->decimal('declared_total_net_weight', 10, 2)->nullable()->after('declared_total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_material_receptions', function (Blueprint $table) {
            $table->dropColumn('declared_total_net_weight');
        });
    }
};
