<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Índices adicionales para optimizar listados filtrados según auditoría (2026-02-15).
     * - punch_events: índice en timestamp para queries por rango de fechas (dashboard, calendar, statistics).
     * - productions: índice en date para listados filtrados por fecha.
     */
    public function up(): void
    {
        // punch_events: timestamp para date range queries (PunchEventListService filters dates)
        if (Schema::hasTable('punch_events') && ! $this->columnHasIndex('punch_events', 'timestamp')) {
            Schema::table('punch_events', function (Blueprint $table) {
                $table->index('timestamp');
            });
        }

        // productions: date para listados filtrados por fecha
        if (Schema::hasTable('productions') && ! $this->columnHasIndex('productions', 'date')) {
            Schema::table('productions', function (Blueprint $table) {
                $table->index('date');
            });
        }
    }

    private function columnHasIndex(string $table, string $column): bool
    {
        $connection = Schema::getConnection()->getDatabaseName();
        $indexes = DB::select(
            "SELECT 1 FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? 
             LIMIT 1",
            [$connection, $table, $column]
        );

        return ! empty($indexes);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('punch_events')) {
            try {
                Schema::table('punch_events', function (Blueprint $table) {
                    $table->dropIndex(['timestamp']);
                });
            } catch (\Throwable $e) {
                // Índice puede tener otro nombre o no existir
            }
        }

        if (Schema::hasTable('productions')) {
            try {
                Schema::table('productions', function (Blueprint $table) {
                    $table->dropIndex(['date']);
                });
            } catch (\Throwable $e) {
                // Índice puede tener otro nombre o no existir
            }
        }
    }
};
