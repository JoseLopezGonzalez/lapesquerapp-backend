<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * IMPORTANTE: Ejecutar solo después de:
     * 1. Verificar que todos los datos fueron migrados (articles.name -> products.name)
     * 2. Verificar que el código funciona correctamente sin Article
     * 3. Hacer backup completo de la BD
     */
    public function up(): void
    {
        Schema::dropIfExists('articles');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se puede recrear automáticamente, se necesitaría restaurar desde backup
        // Si necesitas revertir, usa el backup de BD completo
    }
};

