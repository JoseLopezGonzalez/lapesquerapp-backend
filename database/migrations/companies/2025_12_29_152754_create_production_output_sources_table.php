<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Crea la tabla production_output_sources para rastrear la proveniencia
     * de cada output (de qué inputs proviene y en qué proporción).
     */
    public function up(): void
    {
        // Solo ejecutar en contexto tenant
        if (config('database.default') !== 'tenant') {
            return;
        }

        if (Schema::hasTable('production_output_sources')) {
            return;
        }

        if (!Schema::hasTable('production_outputs')) {
            return;
        }

        Schema::create('production_output_sources', function (Blueprint $table) {
            $table->id();
            
            // Output al que contribuye esta fuente
            $table->unsignedBigInteger('production_output_id');
            $table->foreign('production_output_id', 'pos_output_id_fk')
                  ->references('id')
                  ->on('production_outputs')
                  ->onDelete('cascade');
            
            // Tipo de fuente
            $table->enum('source_type', [
                'stock_box',      // Caja del stock (ProductionInput)
                'parent_output'   // Output del proceso padre (ProductionOutputConsumption)
            ]);
            
            // Si es stock_box, referencia al ProductionInput
            $table->unsignedBigInteger('production_input_id')->nullable();
            
            // Si es parent_output, referencia al ProductionOutputConsumption
            $table->unsignedBigInteger('production_output_consumption_id')->nullable();
            
            // Cantidad de peso (kg) que aporta esta fuente al output
            // ⚠️ Puede ser null si se especifica solo el porcentaje
            $table->decimal('contributed_weight_kg', 10, 2)->nullable();
            
            // Cantidad de cajas que aporta esta fuente (si aplica)
            $table->integer('contributed_boxes')->default(0);
            
            // Porcentaje del output que proviene de esta fuente (0-100)
            // ⚠️ Puede ser null si se especifica solo el peso
            $table->decimal('contribution_percentage', 5, 2)->nullable();
            
            $table->timestamps();
            
            // Índices (nombres acortados para evitar límite de 64 caracteres de MySQL)
            $table->index('production_output_id', 'pos_output_id_idx');
            $table->index(['source_type', 'production_input_id'], 'pos_source_type_input_idx');
            $table->index(['source_type', 'production_output_consumption_id'], 'pos_source_type_consumption_idx');
            
            // Constraints: Solo uno de los dos IDs debe estar presente según source_type
            // Esto se validará a nivel de aplicación
        });

        // Crear foreign keys solo si las tablas existen
        if (Schema::hasTable('production_inputs')) {
            Schema::table('production_output_sources', function (Blueprint $table) {
                $table->foreign('production_input_id', 'pos_input_id_fk')
                      ->references('id')
                      ->on('production_inputs')
                      ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('production_output_consumptions')) {
            Schema::table('production_output_sources', function (Blueprint $table) {
                $table->foreign('production_output_consumption_id', 'pos_consumption_id_fk')
                      ->references('id')
                      ->on('production_output_consumptions')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Solo ejecutar en contexto tenant
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::dropIfExists('production_output_sources');
    }
};
