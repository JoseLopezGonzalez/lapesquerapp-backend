<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX_NAME = 'orders_status_load_date_index';

    /**
     * Run the migrations.
     *
     * Índice compuesto para GET orders/active (filtro status + load_date, orden por load_date).
     * Ref.: docs/referencia/102-Plan-Mejoras-GET-orders-active.md (Mejora A)
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if ($this->indexExists('orders', self::INDEX_NAME)) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'load_date'], self::INDEX_NAME);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection()->getDatabaseName();
        $result = DB::select(
            'SELECT 1 FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$connection, $table, $indexName]
        );

        return ! empty($result);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(self::INDEX_NAME);
            });
        } catch (\Throwable $e) {
            // Índice puede no existir
        }
    }
};
