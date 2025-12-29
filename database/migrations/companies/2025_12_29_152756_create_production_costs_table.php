<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Crea la tabla production_costs para almacenar costes adicionales
     * (producción, personal, operativos, envases) a nivel de proceso o producción.
     */
    public function up(): void
    {
        // Solo ejecutar en contexto tenant
        if (config('database.default') !== 'tenant') {
            return;
        }

        if (Schema::hasTable('production_costs')) {
            return;
        }

        Schema::create('production_costs', function (Blueprint $table) {
            $table->id();
            
            // ⚠️ IMPORTANTE: Solo uno de los dos debe estar presente
            // Nivel de proceso (coste específico de un proceso)
            $table->unsignedBigInteger('production_record_id')->nullable();
            
            // Nivel de producción (coste general del lote completo)
            $table->unsignedBigInteger('production_id')->nullable();
            
            // ⚠️ IMPORTANTE: Referencia al catálogo de costes (si viene del catálogo)
            $table->unsignedBigInteger('cost_catalog_id')->nullable();
            
            // Tipo de coste (categoría general)
            // Se obtiene del catálogo si cost_catalog_id está presente, sino se especifica manualmente
            $table->enum('cost_type', [
                'production',    // Costes de producción (maquinaria, energía, etc.)
                'labor',         // Costes de personal
                'operational',   // Costes operativos (mantenimiento, servicios, etc.)
                'packaging'      // Costes de envases
            ]);
            
            // ⚠️ IMPORTANTE: Nombre del coste
            // - Si cost_catalog_id está presente: Se obtiene del catálogo (pero se puede sobrescribir)
            // - Si cost_catalog_id es null: Nombre libre (coste ad-hoc)
            $table->string('name');
            $table->string('description')->nullable(); // Descripción adicional opcional
            
            // ⚠️ IMPORTANTE: El coste puede especificarse de dos formas:
            // 1. Coste total (total_cost): Se distribuye proporcionalmente al peso de outputs
            // 2. Coste por kg (cost_per_kg): Se multiplica por el peso total de outputs del proceso/producción
            
            // Coste total (si se especifica, cost_per_kg debe ser null)
            $table->decimal('total_cost', 10, 2)->nullable();
            
            // Coste por kg (si se especifica, total_cost debe ser null)
            // Se multiplica por el peso total de outputs para obtener el coste total
            $table->decimal('cost_per_kg', 10, 2)->nullable();
            
            // Unidad de medida para distribuir el coste (opcional, solo si total_cost está presente)
            // Si es null, se distribuye proporcionalmente al peso de outputs
            $table->string('distribution_unit')->nullable(); // 'per_kg', 'per_box', 'per_hour', etc.
            
            // Fecha del coste
            $table->date('cost_date')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('production_record_id');
            $table->index('production_id');
            $table->index('cost_catalog_id');
            $table->index('cost_type');
        });

        // Crear foreign keys solo si las tablas existen
        if (Schema::hasTable('production_records')) {
            Schema::table('production_costs', function (Blueprint $table) {
                $table->foreign('production_record_id')
                      ->references('id')
                      ->on('production_records')
                      ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('productions')) {
            Schema::table('production_costs', function (Blueprint $table) {
                $table->foreign('production_id')
                      ->references('id')
                      ->on('productions')
                      ->onDelete('cascade');
            });
        }

        // Foreign key a cost_catalog (se crea después de que exista la tabla)
        if (Schema::hasTable('cost_catalog')) {
            Schema::table('production_costs', function (Blueprint $table) {
                $table->foreign('cost_catalog_id')
                      ->references('id')
                      ->on('cost_catalog')
                      ->onDelete('set null'); // Si se elimina del catálogo, se mantiene el registro pero sin referencia
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

        Schema::dropIfExists('production_costs');
    }
};
