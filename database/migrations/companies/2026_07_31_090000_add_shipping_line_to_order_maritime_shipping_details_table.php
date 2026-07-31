<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_maritime_shipping_details', function (Blueprint $table) {
            $table->string('shipping_line')->nullable()->after('customs_broker_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_maritime_shipping_details', function (Blueprint $table) {
            $table->dropColumn('shipping_line');
        });
    }
};
