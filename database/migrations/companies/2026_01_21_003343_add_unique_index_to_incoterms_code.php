<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega constraint único en code para asegurar que no se puedan crear
     * múltiples incoterms con el mismo código dentro del mismo tenant.
     */
    public function up(): void
    {
        if (!Schema::hasTable('incoterms')) {
            return;
        }

        // Verificar si ya existe el constraint único en code
        $hasCodeUnique = false;
        
        try {
            $indexes = \DB::select("SHOW INDEX FROM incoterms WHERE Key_name != 'PRIMARY'");
            foreach ($indexes as $index) {
                if ($index->Non_unique == 0 && $index->Column_name === 'code') {
                    $hasCodeUnique = true;
                    break;
                }
            }
        } catch (\Exception $e) {
            // Si falla la consulta, asumimos que no existe y lo creamos
        }
        
        if (!$hasCodeUnique) {
            Schema::table('incoterms', function (Blueprint $table) {
                try {
                    $table->unique('code');
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
        if (!Schema::hasTable('incoterms')) {
            return;
        }

        Schema::table('incoterms', function (Blueprint $table) {
            try {
                $table->dropUnique(['code']);
            } catch (\Exception $e) {
                // El constraint no existe, continuar
            }
        });
    }
};
