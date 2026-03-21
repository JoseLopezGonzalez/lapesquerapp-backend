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
              AND TABLE_NAME = 'customers'
              AND COLUMN_NAME = 'salesperson_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($foreignKeys as $fk) {
            try {
                DB::statement("ALTER TABLE customers DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Throwable $e) {
            }
        }

        DB::statement("ALTER TABLE customers MODIFY salesperson_id BIGINT UNSIGNED NULL");

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('field_operator_id')->nullable()->after('salesperson_id')->constrained('field_operators')->nullOnDelete();
            $table->string('operational_status', 32)->default('normal')->after('field_operator_id');
            $table->foreignId('created_by_user_id')->nullable()->after('operational_status')->constrained('users')->nullOnDelete();
            $table->index(['field_operator_id', 'operational_status']);
            $table->index(['salesperson_id', 'operational_status']);
            $table->foreign('salesperson_id')
                ->references('id')
                ->on('salespeople')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['field_operator_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['salesperson_id']);
            $table->dropIndex(['field_operator_id', 'operational_status']);
            $table->dropIndex(['salesperson_id', 'operational_status']);
            $table->dropColumn(['field_operator_id', 'operational_status', 'created_by_user_id']);
        });

        if (DB::table('customers')->whereNull('salesperson_id')->exists()) {
            throw new \RuntimeException('No se puede revertir customers.salesperson_id a NOT NULL mientras existan clientes sin owner comercial.');
        }

        DB::statement("ALTER TABLE customers MODIFY salesperson_id BIGINT UNSIGNED NOT NULL");

        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('salesperson_id')
                ->references('id')
                ->on('salespeople')
                ->restrictOnDelete();
        });
    }
};
