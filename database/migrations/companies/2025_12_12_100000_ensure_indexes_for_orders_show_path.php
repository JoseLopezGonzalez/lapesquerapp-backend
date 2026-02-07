<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Asegura índices en las columnas usadas por GET orders/{id} (eager loading).
     * Solo añade índice si la columna aún no tiene ninguno (las FK suelen crearlos).
     * Ref.: docs/referencia/101-Plan-Mejoras-GET-orders-id.md (Mejora 5)
     */
    public function up(): void
    {
        $tables = [
            'pallets' => ['order_id'],
            'pallet_boxes' => ['pallet_id', 'box_id'],
            'boxes' => ['article_id'],
            'production_inputs' => ['box_id'], // ya tiene índice en create; por si se perdió
            'order_planned_product_details' => ['order_id'],
            'incidents' => ['order_id'],
        ];

        foreach ($tables as $tableName => $columns) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($tableName, $column)) {
                    continue;
                }

                if ($this->columnHasIndex($tableName, $column)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($column) {
                    $table->index($column);
                });
            }
        }
    }

    /**
     * Comprueba si la columna tiene ya algún índice (incl. el implícito de una FK).
     */
    private function columnHasIndex(string $table, string $column): bool
    {
        $connection = Schema::getConnection()->getDatabaseName();
        $indexes = DB::select(
            "SELECT 1 FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? 
             LIMIT 1",
            [$connection, $table, $column]
        );

        return !empty($indexes);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $drops = [
            'pallets' => ['order_id'],
            'pallet_boxes' => ['pallet_id', 'box_id'],
            'boxes' => ['article_id'],
            'production_inputs' => ['box_id'],
            'order_planned_product_details' => ['order_id'],
            'incidents' => ['order_id'],
        ];

        foreach ($drops as $tableName => $columns) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($columns as $column) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($column) {
                        $table->dropIndex([$column]);
                    });
                } catch (\Throwable $e) {
                    // El índice puede tener otro nombre (p. ej. de una FK); ignorar
                }
            }
        }
    }
};
