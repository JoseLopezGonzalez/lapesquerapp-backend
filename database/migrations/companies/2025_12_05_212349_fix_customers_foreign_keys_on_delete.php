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

        // Get existing foreign key names
        $foreignKeys = \DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'customers' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Drop existing foreign keys using raw SQL
        foreach ($foreignKeys as $fk) {
            try {
                \DB::statement("ALTER TABLE customers DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // FK does not exist or already dropped, continue
            }
        }

        Schema::table('customers', function (Blueprint $table) {
            // payment_term_id: restrict
            if (Schema::hasTable('payment_terms')) {
                $table->foreign('payment_term_id')
                    ->references('id')
                    ->on('payment_terms')
                    ->onDelete('restrict');
            }

            // salesperson_id: restrict
            if (Schema::hasTable('salespersons')) {
                $table->foreign('salesperson_id')
                    ->references('id')
                    ->on('salespersons')
                    ->onDelete('restrict');
            }

            // country_id: restrict
            if (Schema::hasTable('countries')) {
                $table->foreign('country_id')
                    ->references('id')
                    ->on('countries')
                    ->onDelete('restrict');
            }

            // transport_id: restrict
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
     */
    public function down(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $foreignKeys = ['payment_term_id', 'salesperson_id', 'country_id', 'transport_id'];
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
