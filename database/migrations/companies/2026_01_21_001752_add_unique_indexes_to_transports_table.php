<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega constraints únicos en name y vat_number para asegurar que no se puedan crear
     * múltiples transportes con el mismo nombre o NIF dentro del mismo tenant.
     */
    public function up(): void
    {
        if (!Schema::hasTable('transports')) {
            return;
        }

        // Verificar si ya existe el constraint único en name
        $hasNameUnique = false;
        $hasVatNumberUnique = false;
        
        try {
            $indexes = \DB::select("SHOW INDEX FROM transports WHERE Key_name != 'PRIMARY'");
            foreach ($indexes as $index) {
                if ($index->Non_unique == 0 && $index->Column_name === 'name') {
                    $hasNameUnique = true;
                }
                if ($index->Non_unique == 0 && $index->Column_name === 'vat_number') {
                    $hasVatNumberUnique = true;
                }
            }
        } catch (\Exception $e) {
            // Si falla la consulta, asumimos que no existe y lo creamos
        }
        
        Schema::table('transports', function (Blueprint $table) use ($hasNameUnique, $hasVatNumberUnique) {
            if (!$hasNameUnique) {
                try {
                    $table->unique('name');
                } catch (\Exception $e) {
                    // El constraint ya existe, continuar
                }
            }
            
            if (!$hasVatNumberUnique) {
                try {
                    $table->unique('vat_number');
                } catch (\Exception $e) {
                    // El constraint ya existe, continuar
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('transports')) {
            return;
        }

        Schema::table('transports', function (Blueprint $table) {
            try {
                $table->dropUnique(['name']);
            } catch (\Exception $e) {
                // El constraint no existe, continuar
            }
            
            try {
                $table->dropUnique(['vat_number']);
            } catch (\Exception $e) {
                // El constraint no existe, continuar
            }
        });
    }
};
