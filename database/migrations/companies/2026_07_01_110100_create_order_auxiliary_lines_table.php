<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_auxiliary_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('auxiliary_product_id')->nullable()->constrained('auxiliary_products')->nullOnDelete();
            $table->string('description', 500)->nullable(); // Libre si no hay producto de catálogo
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 50)->default('ud'); // kg, ud, saco, palet, servicio...
            $table->decimal('unit_price', 10, 4)->default(0);
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->timestamps();

            $table->index('order_id');
            $table->index('auxiliary_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_auxiliary_lines');
    }
};
