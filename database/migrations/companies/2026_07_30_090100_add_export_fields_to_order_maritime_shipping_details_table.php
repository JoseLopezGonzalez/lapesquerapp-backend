<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_maritime_shipping_details', function (Blueprint $table) {
            $table->foreignId('customs_broker_id')->nullable()->after('order_id')->constrained('customs_brokers')->nullOnDelete();
            $table->string('ultimate_consignee_name')->nullable()->after('discharge_port');
            $table->text('ultimate_consignee_address')->nullable()->after('ultimate_consignee_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_maritime_shipping_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customs_broker_id');
            $table->dropColumn(['ultimate_consignee_name', 'ultimate_consignee_address']);
        });
    }
};
