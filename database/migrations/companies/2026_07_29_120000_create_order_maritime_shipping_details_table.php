<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_maritime_shipping_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('vessel_name')->nullable();
            $table->string('voyage_number')->nullable();
            $table->string('export_invoice_number')->nullable();
            $table->string('swb_number')->nullable();
            $table->string('loading_port')->nullable();
            $table->string('discharge_port')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_maritime_shipping_details');
    }
};
