<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_maritime_shipping_details', function (Blueprint $table) {
            $table->string('booking_number')->nullable()->after('export_invoice_number');
            $table->string('origin_country')->nullable()->after('discharge_port');
            $table->string('destination_country')->nullable()->after('origin_country');
        });
    }

    public function down(): void
    {
        Schema::table('order_maritime_shipping_details', function (Blueprint $table) {
            $table->dropColumn(['booking_number', 'origin_country', 'destination_country']);
        });
    }
};
