<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE orders o
            LEFT JOIN routes r ON r.id = o.route_id
            SET o.route_id = NULL
            WHERE o.route_id IS NOT NULL
              AND r.id IS NULL
        ");

        DB::statement("
            UPDATE orders o
            LEFT JOIN route_stops rs ON rs.id = o.route_stop_id
            SET o.route_stop_id = NULL
            WHERE o.route_stop_id IS NOT NULL
              AND rs.id IS NULL
        ");

        DB::statement("
            UPDATE orders o
            INNER JOIN route_stops rs ON rs.id = o.route_stop_id
            SET o.route_stop_id = NULL
            WHERE o.route_id IS NOT NULL
              AND rs.route_id <> o.route_id
        ");

        $this->dropForeignIfExists('orders', 'route_id');
        $this->dropForeignIfExists('orders', 'route_stop_id');

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('route_id')
                ->references('id')
                ->on('routes')
                ->nullOnDelete();

            $table->foreign('route_stop_id')
                ->references('id')
                ->on('route_stops')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['route_id']);
            $table->dropForeign(['route_stop_id']);
        });
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$table, $column]);

        foreach ($foreignKeys as $foreignKey) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY `{$foreignKey->CONSTRAINT_NAME}`");
        }
    }
};

