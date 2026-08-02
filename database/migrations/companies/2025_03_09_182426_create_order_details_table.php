<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();

            // Relación con la tabla 'orders'
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // Relación con la tabla 'products'
            $table->foreignId('product_id')->constrained('products');

            // Relación con la tabla 'taxes'
            $table->foreignId('tax_id')->nullable()->constrained('taxes');

            // Cantidad en decimal
            $table->decimal('quantity', 10, 3)->default(0);

            // Palets y cajas
            /* $table->decimal('pallets', 10, 2)->default(0); */
            $table->decimal('boxes', 10, 2)->default(0);

            // Precio unitario
            $table->decimal('unit_price', 10, 2)->default(0);

            // Descuento
            /* $table->string('discount_type')->default('fixed'); */ // 'fixed', 'percentage', 'none'
            /* $table->decimal('discount_value', 10, 2)->nullable(); */

            // Total de la línea
            /*  $table->decimal('line_base', 10, 2)->nullable();
             $table->decimal('line_total', 10, 2)->nullable();
 */
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
