<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'orders'
              AND COLUMN_NAME = 'salesperson_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($foreignKeys as $fk) {
            try {
                DB::statement("ALTER TABLE orders DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Throwable $e) {
            }
        }

        DB::statement("ALTER TABLE orders MODIFY salesperson_id BIGINT UNSIGNED NULL");

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('field_operator_id')->nullable()->after('salesperson_id')->constrained('field_operators')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('field_operator_id')->constrained('users')->nullOnDelete();
            $table->foreignId('route_id')->nullable()->after('incoterm_id');
            $table->foreignId('route_stop_id')->nullable()->after('route_id');
            $table->index(['field_operator_id', 'status']);
            $table->index(['route_id', 'route_stop_id']);
            $table->foreign('salesperson_id')
                ->references('id')
                ->on('salespeople')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['field_operator_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['salesperson_id']);
            $table->dropIndex(['field_operator_id', 'status']);
            $table->dropIndex(['route_id', 'route_stop_id']);
            $table->dropColumn(['field_operator_id', 'created_by_user_id', 'route_id', 'route_stop_id']);
        });

        if (DB::table('orders')->whereNull('salesperson_id')->exists()) {
            throw new \RuntimeException('No se puede revertir orders.salesperson_id a NOT NULL mientras existan pedidos sin owner comercial.');
        }

        DB::statement("ALTER TABLE orders MODIFY salesperson_id BIGINT UNSIGNED NOT NULL");

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('salesperson_id')
                ->references('id')
                ->on('salespeople')
                ->restrictOnDelete();
        });
    }
};
