<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega constraint único en box_id para asegurar que una caja
     * solo puede estar en un palet a la vez.
     * También asegura que la combinación ['pallet_id', 'box_id'] sea única.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pallet_boxes')) {
            return;
        }

        // Verificar si ya existe el constraint único en box_id usando SQL directo
        $hasBoxUnique = false;
        $hasCompositeUnique = false;
        
        try {
            $indexes = \DB::select("SHOW INDEX FROM pallet_boxes WHERE Key_name != 'PRIMARY'");
            foreach ($indexes as $index) {
                if ($index->Non_unique == 0) {
                    if ($index->Column_name === 'box_id' && $index->Seq_in_index == 1) {
                        $hasBoxUnique = true;
                    }
                    if ($index->Column_name === 'pallet_id' && $index->Seq_in_index == 1) {
                        // Verificar si es compuesto
                        $compositeIndexes = array_filter($indexes, function($idx) use ($index) {
                            return $idx->Key_name === $index->Key_name && 
                                   ($idx->Column_name === 'box_id' || $idx->Column_name === 'pallet_id');
                        });
                        if (count($compositeIndexes) === 2) {
                            $hasCompositeUnique = true;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Si falla la consulta, asumimos que no existen y los creamos
        }
        
        Schema::table('pallet_boxes', function (Blueprint $table) use ($hasBoxUnique, $hasCompositeUnique) {
            // Agregar constraint único en box_id si no existe
            if (!$hasBoxUnique) {
                try {
                    $table->unique('box_id');
                } catch (\Exception $e) {
                    // El constraint ya existe, continuar
                }
            }
            
            // Agregar constraint único compuesto si no existe
            if (!$hasCompositeUnique) {
                try {
                    $table->unique(['pallet_id', 'box_id']);
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
        Schema::table('pallet_boxes', function (Blueprint $table) {
            // Eliminar los constraints únicos
            try {
                $table->dropUnique(['box_id']);
            } catch (\Exception $e) {
                // El constraint no existe, continuar
            }
            
            try {
                $table->dropUnique(['pallet_id', 'box_id']);
            } catch (\Exception $e) {
                // El constraint no existe, continuar
            }
        });
    }
};
