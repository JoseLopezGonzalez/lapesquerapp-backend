<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Hace nullable payment_term_id, transport_id, billing_address, shipping_address y emails
     * para permitir autoventas que no rellenan esos datos.
     */
    public function up(): void
    {
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'orders'
            AND COLUMN_NAME IN ('payment_term_id', 'transport_id')
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($foreignKeys as $fk) {
            try {
                DB::statement("ALTER TABLE orders DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // FK does not exist, continue
            }
        }

        DB::statement("ALTER TABLE orders
            MODIFY payment_term_id BIGINT UNSIGNED NULL,
            MODIFY transport_id BIGINT UNSIGNED NULL,
            MODIFY billing_address TEXT NULL,
            MODIFY shipping_address TEXT NULL,
            MODIFY emails TEXT NULL
        ");

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasTable('payment_terms')) {
                $table->foreign('payment_term_id')
                    ->references('id')
                    ->on('payment_terms')
                    ->onDelete('restrict');
            }
            if (Schema::hasTable('transports')) {
                $table->foreign('transport_id')
                    ->references('id')
                    ->on('transports')
                    ->onDelete('restrict');
            }
        });
    }

    /**
     * Reverse the migrations.
     * Nota: Si existen pedidos (autoventas) con NULL en estas columnas, el down fallará al hacer NOT NULL.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['payment_term_id']);
            $table->dropForeign(['transport_id']);
        });

        DB::statement("ALTER TABLE orders
            MODIFY payment_term_id BIGINT UNSIGNED NOT NULL,
            MODIFY transport_id BIGINT UNSIGNED NOT NULL,
            MODIFY billing_address TEXT NOT NULL,
            MODIFY shipping_address TEXT NOT NULL,
            MODIFY emails TEXT NOT NULL
        ");

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasTable('payment_terms')) {
                $table->foreign('payment_term_id')
                    ->references('id')
                    ->on('payment_terms')
                    ->onDelete('restrict');
            }
            if (Schema::hasTable('transports')) {
                $table->foreign('transport_id')
                    ->references('id')
                    ->on('transports')
                    ->onDelete('restrict');
            }
        });
    }
};
