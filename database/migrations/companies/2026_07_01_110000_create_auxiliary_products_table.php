<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auxiliary_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('reference', 100)->nullable(); // Código interno o referencia comercial
            $table->string('unit', 50)->default('ud'); // kg, ud, saco, palet, servicio...
            $table->decimal('default_price', 10, 4)->nullable(); // Precio orientativo por unidad
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('name', 'auxiliary_products_name_unique');
            $table->index(['active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auxiliary_products');
    }
};
