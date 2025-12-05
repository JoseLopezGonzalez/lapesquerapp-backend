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
        if (config('database.default') !== 'tenant') {
            return;
        }

        // Clean up invalid foreign key references before adding constraints (only if tables exist)
        if (Schema::hasTable('customers')) {
            \DB::statement("UPDATE orders SET customer_id = NULL WHERE customer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM customers WHERE customers.id = orders.customer_id)");
        }
        if (Schema::hasTable('payment_terms')) {
            \DB::statement("UPDATE orders SET payment_term_id = NULL WHERE payment_term_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM payment_terms WHERE payment_terms.id = orders.payment_term_id)");
        }
        if (Schema::hasTable('salespersons')) {
            \DB::statement("UPDATE orders SET salesperson_id = NULL WHERE salesperson_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM salespersons WHERE salespersons.id = orders.salesperson_id)");
        }
        if (Schema::hasTable('transports')) {
            \DB::statement("UPDATE orders SET transport_id = NULL WHERE transport_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM transports WHERE transports.id = orders.transport_id)");
        }
        if (Schema::hasTable('incoterms')) {
            \DB::statement("UPDATE orders SET incoterm_id = NULL WHERE incoterm_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM incoterms WHERE incoterms.id = orders.incoterm_id)");
        }

        // Get existing foreign key names
        $foreignKeys = \DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'orders' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Drop existing foreign keys using raw SQL
        foreach ($foreignKeys as $fk) {
            try {
                \DB::statement("ALTER TABLE orders DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // FK does not exist or already dropped, continue
            }
        }

        Schema::table('orders', function (Blueprint $table) {
            // customer_id: restrict (no eliminar clientes con pedidos)
            if (Schema::hasTable('customers')) {
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('customers')
                    ->onDelete('restrict');
            }

            // payment_term_id: restrict (no eliminar términos de pago con pedidos)
            if (Schema::hasTable('payment_terms')) {
                $table->foreign('payment_term_id')
                    ->references('id')
                    ->on('payment_terms')
                    ->onDelete('restrict');
            }

            // salesperson_id: restrict (no eliminar vendedores con pedidos)
            if (Schema::hasTable('salespersons')) {
                $table->foreign('salesperson_id')
                    ->references('id')
                    ->on('salespersons')
                    ->onDelete('restrict');
            }

            // transport_id: restrict (no eliminar transportes con pedidos)
            if (Schema::hasTable('transports')) {
                $table->foreign('transport_id')
                    ->references('id')
                    ->on('transports')
                    ->onDelete('restrict');
            }

            // incoterm_id: restrict (no eliminar incoterms con pedidos)
            if (Schema::hasTable('incoterms')) {
                $table->foreign('incoterm_id')
                    ->references('id')
                    ->on('incoterms')
                    ->onDelete('restrict');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $foreignKeys = ['customer_id', 'payment_term_id', 'salesperson_id', 'transport_id', 'incoterm_id'];
            foreach ($foreignKeys as $fk) {
                try {
                    $table->dropForeign([$fk]);
                } catch (\Exception $e) {
                    // FK does not exist, continue
                }
            }
        });
    }
};
