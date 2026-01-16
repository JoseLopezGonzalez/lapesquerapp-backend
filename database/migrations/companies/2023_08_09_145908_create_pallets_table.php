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
        Schema::create('pallets', function (Blueprint $table) {
            $table->id();
            $table->text('observations')->nullable();
            $table->unsignedBigInteger('status')->default(1);
            $table->unsignedBigInteger('order_id')->nullable();
            //$table->unsignedBigInteger('store_id')->nullable();
            //$table->foreign('store_id')->references('id')->on('stores')->onDelete('set null');    
            // La foreign key a orders se agrega en una migración posterior (2025_12_05_210346)
            // porque orders se crea después de pallets
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pallets');
    }
};
