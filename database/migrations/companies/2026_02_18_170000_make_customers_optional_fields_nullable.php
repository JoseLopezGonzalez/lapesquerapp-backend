<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Hace nullable vat_number, payment_term_id, billing_address, shipping_address,
     * emails, contact_info, country_id y transport_id para permitir creación de
     * cliente "in situ" en autoventa (solo nombre obligatorio).
     */
    public function up(): void
    {
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'customers'
            AND COLUMN_NAME IN ('payment_term_id', 'country_id', 'transport_id')
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($foreignKeys as $fk) {
            try {
                DB::statement("ALTER TABLE customers DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // FK does not exist, continue
            }
        }

        DB::statement("ALTER TABLE customers
            MODIFY vat_number VARCHAR(255) NULL,
            MODIFY payment_term_id BIGINT UNSIGNED NULL,
            MODIFY billing_address TEXT NULL,
            MODIFY shipping_address TEXT NULL,
            MODIFY emails TEXT NULL,
            MODIFY contact_info TEXT NULL,
            MODIFY country_id BIGINT UNSIGNED NULL,
            MODIFY transport_id BIGINT UNSIGNED NULL
        ");

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasTable('payment_terms')) {
                $table->foreign('payment_term_id')
                    ->references('id')
                    ->on('payment_terms')
                    ->onDelete('restrict');
            }
            if (Schema::hasTable('countries')) {
                $table->foreign('country_id')
                    ->references('id')
                    ->on('countries')
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
     * Nota: Si existen clientes con NULL en estas columnas, el down fallará al hacer NOT NULL.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['payment_term_id']);
            $table->dropForeign(['country_id']);
            $table->dropForeign(['transport_id']);
        });

        DB::statement("ALTER TABLE customers
            MODIFY vat_number VARCHAR(255) NOT NULL,
            MODIFY payment_term_id BIGINT UNSIGNED NOT NULL,
            MODIFY billing_address TEXT NOT NULL,
            MODIFY shipping_address TEXT NOT NULL,
            MODIFY emails TEXT NOT NULL,
            MODIFY contact_info TEXT NOT NULL,
            MODIFY country_id BIGINT UNSIGNED NOT NULL,
            MODIFY transport_id BIGINT UNSIGNED NOT NULL
        ");

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasTable('payment_terms')) {
                $table->foreign('payment_term_id')
                    ->references('id')
                    ->on('payment_terms')
                    ->onDelete('restrict');
            }
            if (Schema::hasTable('countries')) {
                $table->foreign('country_id')
                    ->references('id')
                    ->on('countries')
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
