<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Verificar que la tabla processes existe antes de modificarla
        if (Schema::hasTable('processes')) {
            Schema::table('processes', function (Blueprint $table) {
                // Verificar que la columna no existe ya
                if (!Schema::hasColumn('processes', 'species_id')) {
                    $table->unsignedBigInteger('species_id');
                }
            });

            // Agregar foreign key solo si species existe
            if (Schema::hasTable('species')) {
                Schema::table('processes', function (Blueprint $table) {
                    // Eliminar FK existente si existe
                    try {
                        $table->dropForeign(['species_id']);
                    } catch (\Exception $e) {
                        // FK no existe, continuar
                    }
                });

                Schema::table('processes', function (Blueprint $table) {
                    $table->foreign('species_id')->references('id')->on('species')->onDelete('cascade');
                });
            }
        }
    }

    public function down()
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropForeign(['species_id']);
            $table->dropColumn('species_id');
        });
    }
};
