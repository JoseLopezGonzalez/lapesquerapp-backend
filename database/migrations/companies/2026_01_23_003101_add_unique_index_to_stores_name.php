<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega constraint único en name para asegurar que no se puedan crear
     * múltiples almacenes con el mismo nombre dentro del mismo tenant.
     */
    public function up(): void
    {
        if (!Schema::hasTable('stores')) {
            return;
        }

        // Verificar si ya existe el constraint único en name
        $hasNameUnique = false;
        
        try {
            $indexes = \DB::select("SHOW INDEX FROM stores WHERE Key_name != 'PRIMARY'");
            foreach ($indexes as $index) {
                if ($index->Non_unique == 0 && $index->Column_name === 'name') {
                    $hasNameUnique = true;
                    break;
                }
            }
        } catch (\Exception $e) {
            // Si falla la consulta, asumimos que no existe y lo creamos
        }
        
        if (!$hasNameUnique) {
            Schema::table('stores', function (Blueprint $table) {
                try {
                    $table->unique('name');
                } catch (\Exception $e) {
                    // El constraint ya existe, continuar
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('stores')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            try {
                $table->dropUnique(['name']);
            } catch (\Exception $e) {
                // El constraint no existe, continuar
            }
        });
    }
};
