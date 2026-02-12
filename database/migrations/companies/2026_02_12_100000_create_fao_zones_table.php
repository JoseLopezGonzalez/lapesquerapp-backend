<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zonas FAO (por tenant). Referencia: Guía Entorno Desarrollo PesquerApp.
     */
    public function up(): void
    {
        Schema::create('fao_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::table('fao_zones', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('fao_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fao_zones');
    }
};
