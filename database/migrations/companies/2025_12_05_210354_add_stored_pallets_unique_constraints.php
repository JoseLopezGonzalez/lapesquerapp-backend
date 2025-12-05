<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega constraint único en pallet_id para asegurar que un palet
     * solo puede estar almacenado en un almacén a la vez.
     */
    public function up(): void
    {
        if (!Schema::hasTable('stored_pallets')) {
            return;
        }

        // Verificar si ya existe el constraint único en pallet_id usando SQL directo
        $hasPalletUnique = false;
        
        try {
            $indexes = \DB::select("SHOW INDEX FROM stored_pallets WHERE Key_name != 'PRIMARY'");
            foreach ($indexes as $index) {
                if ($index->Non_unique == 0 && $index->Column_name === 'pallet_id') {
                    $hasPalletUnique = true;
                    break;
                }
            }
        } catch (\Exception $e) {
            // Si falla la consulta, asumimos que no existe y lo creamos
        }
        
        Schema::table('stored_pallets', function (Blueprint $table) use ($hasPalletUnique) {
            // Agregar constraint único en pallet_id si no existe
            if (!$hasPalletUnique) {
                try {
                    $table->unique('pallet_id');
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
        Schema::table('stored_pallets', function (Blueprint $table) {
            // Eliminar el constraint único
            try {
                $table->dropUnique(['pallet_id']);
            } catch (\Exception $e) {
                // El constraint no existe, continuar
            }
        });
    }
};
