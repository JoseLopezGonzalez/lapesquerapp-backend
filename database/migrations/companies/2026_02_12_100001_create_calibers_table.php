<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Calibres por especie (por tenant). Referencia: Guía Entorno Desarrollo PesquerApp.
     */
    public function up(): void
    {
        Schema::create('calibers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('min_weight')->nullable()->comment('Gramos');
            $table->unsignedInteger('max_weight')->nullable()->comment('Gramos');
            $table->string('species', 50)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibers');
    }
};
