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
        Schema::create('production_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_record_id')->constrained('production_records')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('lot_id')->nullable(); // Lote como string (opcional)
            $table->integer('boxes')->default(0); // Cantidad de cajas producidas
            $table->decimal('weight_kg', 10, 2)->default(0); // Peso en kilogramos
            $table->timestamps();
            
            // Índices para mejorar rendimiento
            $table->index('production_record_id');
            $table->index('product_id');
            $table->index('lot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_outputs');
    }
};
