<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cebo_dispatches', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_liquidation_id')->nullable()->after('export_type');
            $table->foreign('supplier_liquidation_id')->references('id')->on('supplier_liquidations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cebo_dispatches', function (Blueprint $table) {
            $table->dropForeign(['supplier_liquidation_id']);
            $table->dropColumn('supplier_liquidation_id');
        });
    }
};
