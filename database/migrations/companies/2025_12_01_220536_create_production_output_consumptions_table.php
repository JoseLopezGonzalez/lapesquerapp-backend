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
        Schema::create('production_output_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_record_id')->constrained('production_records')->onDelete('cascade');
            $table->foreignId('production_output_id')->constrained('production_outputs')->onDelete('cascade');
            $table->decimal('consumed_weight_kg', 10, 2)->default(0);
            $table->integer('consumed_boxes')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Índices para mejorar rendimiento
            $table->index('production_record_id');
            $table->index('production_output_id');
            
            // Evitar duplicados: un proceso no puede consumir el mismo output múltiples veces
            // Nota: Si necesitas consumos parciales múltiples, esto debería ser único por (production_record_id, production_output_id)
            // Por ahora asumimos que un proceso puede consumir un output solo una vez, pero puede ser parcial
            $table->unique(['production_record_id', 'production_output_id'], 'unique_record_output');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_output_consumptions');
    }
};

