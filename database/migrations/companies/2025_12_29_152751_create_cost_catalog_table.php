<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Crea la tabla cost_catalog para almacenar el catálogo de costes comunes.
     * Esto evita inconsistencias en nombres y facilita el análisis.
     */
    public function up(): void
    {
        // Solo ejecutar en contexto tenant
        if (config('database.default') !== 'tenant') {
            return;
        }

        if (Schema::hasTable('cost_catalog')) {
            return;
        }

        Schema::create('cost_catalog', function (Blueprint $table) {
            $table->id();
            
            // Nombre del coste (único)
            $table->string('name')->unique();
            
            // Tipo de coste (categoría)
            $table->enum('cost_type', [
                'production',    // Costes de producción (maquinaria, energía, etc.)
                'labor',         // Costes de personal
                'operational',   // Costes operativos (mantenimiento, servicios, etc.)
                'packaging'      // Costes de envases
            ]);
            
            // Descripción del coste
            $table->text('description')->nullable();
            
            // Unidad por defecto (total o per_kg)
            // Indica cómo se suele especificar este coste
            $table->enum('default_unit', ['total', 'per_kg'])->default('total');
            
            // Si está activo (permite desactivar costes sin eliminar)
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Índices
            $table->index('cost_type');
            $table->index('is_active');
        });
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

        Schema::dropIfExists('cost_catalog');
    }
};
