<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Asegura que existe la foreign key para order_id en pallets.
     * Si la columna no existe, la crea. Si existe pero no tiene FK, la agrega.
     * onDelete('set null') permite que un palet quede sin pedido si se elimina el pedido.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pallets')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table) {
            // Verificar si la columna existe, si no, crearla
            if (!Schema::hasColumn('pallets', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('status');
            }
        });
        
        // Eliminar FK existente si existe (puede tener nombre diferente)
        try {
            Schema::table('pallets', function (Blueprint $table) {
                $table->dropForeign(['order_id']);
            });
        } catch (\Exception $e) {
            // La FK no existe o tiene otro nombre, continuar
        }
        
        // Verificar que la tabla orders existe antes de crear la FK
        if (Schema::hasTable('orders')) {
            // Crear la foreign key con onDelete('set null')
            Schema::table('pallets', function (Blueprint $table) {
                $table->foreign('order_id')
                    ->references('id')
                    ->on('orders')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            // Eliminar la foreign key
            try {
                $table->dropForeign(['order_id']);
            } catch (\Exception $e) {
                // La FK no existe, continuar
            }
        });
    }
};
