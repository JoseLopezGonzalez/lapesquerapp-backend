<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->text('buyer_reference')->nullable();
            $table->foreignId('customer_id')->constrained(); // Clave foránea a customers
            $table->foreignId('payment_term_id')->constrained(); // Clave foránea a payment_terms
            $table->text('billing_address');
            $table->text('shipping_address');
            $table->text('transportation_notes')->nullable();
            $table->text('production_notes')->nullable();
            $table->text('accounting_notes')->nullable();
            $table->foreignId('salesperson_id')->constrained(); // Clave foránea a salespersons
            $table->text('emails'); // Asumiendo que quieres almacenar varios emails en un campo JSON
            $table->foreignId('transport_id')->constrained(); // Clave foránea a transports
            $table->date('entry_date'); // Fecha de entrada
            $table->date('load_date'); // Fecha de carga
            $table->string('status'); // Estado del pedido
            $table->unsignedBigInteger('incoterm_id')->nullable(); // Clave foránea a incoterms (se agrega en migración posterior)
            // La foreign key a incoterms se agrega en una migración posterior (2025_12_05_212339)
            // porque incoterms se crea después de orders
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
