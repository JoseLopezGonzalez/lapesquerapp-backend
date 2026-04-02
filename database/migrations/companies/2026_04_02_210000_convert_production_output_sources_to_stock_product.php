<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function foreignKeyExists(string $table, string $constraintName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->exists();
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    public function up(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        if (! Schema::hasTable('production_output_sources')) {
            return;
        }

        DB::statement("ALTER TABLE production_output_sources MODIFY COLUMN source_type ENUM('stock_box', 'stock_product', 'parent_output') NOT NULL");

        Schema::table('production_output_sources', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('source_type');
            $table->index(['source_type', 'product_id'], 'pos_source_type_product_idx');
        });

        if (Schema::hasTable('products')) {
            Schema::table('production_output_sources', function (Blueprint $table) {
                $table->foreign('product_id', 'pos_product_id_fk')
                    ->references('id')
                    ->on('products')
                    ->onDelete('cascade');
            });
        }

        $legacySources = DB::table('production_output_sources as pos')
            ->join('production_inputs as pi', 'pi.id', '=', 'pos.production_input_id')
            ->join('boxes as b', 'b.id', '=', 'pi.box_id')
            ->where('pos.source_type', 'stock_box')
            ->select('pos.id', 'b.article_id as product_id')
            ->orderBy('pos.id')
            ->get();

        foreach ($legacySources as $legacySource) {
            DB::table('production_output_sources')
                ->where('id', $legacySource->id)
                ->update([
                    'product_id' => $legacySource->product_id,
                    'source_type' => 'stock_product',
                ]);
        }

        $remainingLegacyCount = DB::table('production_output_sources')
            ->where('source_type', 'stock_box')
            ->count();

        if ($remainingLegacyCount > 0) {
            throw new RuntimeException("No se pudieron migrar {$remainingLegacyCount} source(s) legacy de tipo stock_box a stock_product.");
        }

        $duplicates = DB::table('production_output_sources')
            ->select(
                'production_output_id',
                'product_id',
                'source_type',
                DB::raw('COUNT(*) as aggregate_count')
            )
            ->where('source_type', 'stock_product')
            ->groupBy('production_output_id', 'product_id', 'source_type')
            ->having('aggregate_count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('production_output_sources')
                ->where('production_output_id', $duplicate->production_output_id)
                ->where('product_id', $duplicate->product_id)
                ->where('source_type', $duplicate->source_type)
                ->orderBy('id')
                ->get();

            $keeper = $rows->first();
            if (! $keeper) {
                continue;
            }

            DB::table('production_output_sources')
                ->where('id', $keeper->id)
                ->update([
                    'contributed_weight_kg' => $rows->sum(fn ($row) => (float) ($row->contributed_weight_kg ?? 0)),
                    'contribution_percentage' => $rows->sum(fn ($row) => (float) ($row->contribution_percentage ?? 0)),
                    'contributed_boxes' => $rows->sum(fn ($row) => (int) ($row->contributed_boxes ?? 0)),
                ]);

            DB::table('production_output_sources')
                ->whereIn('id', $rows->pluck('id')->skip(1))
                ->delete();
        }

        Schema::table('production_output_sources', function (Blueprint $table) {
            if ($this->foreignKeyExists('production_output_sources', 'pos_input_id_fk')) {
                $table->dropForeign('pos_input_id_fk');
            }

            if ($this->indexExists('production_output_sources', 'pos_source_type_input_idx')) {
                $table->dropIndex('pos_source_type_input_idx');
            }

            if (Schema::hasColumn('production_output_sources', 'production_input_id')) {
                $table->dropColumn('production_input_id');
            }
        });

        DB::statement("ALTER TABLE production_output_sources MODIFY COLUMN source_type ENUM('stock_product', 'parent_output') NOT NULL");
    }

    public function down(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        if (! Schema::hasTable('production_output_sources')) {
            return;
        }

        DB::statement("ALTER TABLE production_output_sources MODIFY COLUMN source_type ENUM('stock_box', 'stock_product', 'parent_output') NOT NULL");

        Schema::table('production_output_sources', function (Blueprint $table) {
            $table->unsignedBigInteger('production_input_id')->nullable()->after('product_id');
            $table->index(['source_type', 'production_input_id'], 'pos_source_type_input_idx');
        });

        if (Schema::hasTable('production_inputs')) {
            Schema::table('production_output_sources', function (Blueprint $table) {
                $table->foreign('production_input_id', 'pos_input_id_fk')
                    ->references('id')
                    ->on('production_inputs')
                    ->onDelete('cascade');
            });
        }

        DB::table('production_output_sources')
            ->where('source_type', 'stock_product')
            ->update(['source_type' => 'stock_box']);

        $stockProductSources = DB::table('production_output_sources as pos')
            ->join('production_outputs as po', 'po.id', '=', 'pos.production_output_id')
            ->join('production_inputs as pi', 'pi.production_record_id', '=', 'po.production_record_id')
            ->join('boxes as b', 'b.id', '=', 'pi.box_id')
            ->where('pos.source_type', 'stock_box')
            ->whereColumn('b.article_id', 'pos.product_id')
            ->select('pos.id as source_id', 'pi.id as production_input_id')
            ->orderBy('pi.id')
            ->get()
            ->groupBy('source_id')
            ->map(fn ($rows) => $rows->first());

        foreach ($stockProductSources as $sourceId => $row) {
            DB::table('production_output_sources')
                ->where('id', $sourceId)
                ->update(['production_input_id' => $row->production_input_id]);
        }

        $unresolvedRollbackCount = DB::table('production_output_sources')
            ->where('source_type', 'stock_box')
            ->whereNull('production_input_id')
            ->count();

        if ($unresolvedRollbackCount > 0) {
            throw new RuntimeException("No se pudieron reconstruir {$unresolvedRollbackCount} source(s) legacy stock_box durante el rollback.");
        }

        Schema::table('production_output_sources', function (Blueprint $table) {
            if ($this->foreignKeyExists('production_output_sources', 'pos_product_id_fk')) {
                $table->dropForeign('pos_product_id_fk');
            }

            if ($this->indexExists('production_output_sources', 'pos_source_type_product_idx')) {
                $table->dropIndex('pos_source_type_product_idx');
            }

            if (Schema::hasColumn('production_output_sources', 'product_id')) {
                $table->dropColumn('product_id');
            }
        });

        DB::statement("ALTER TABLE production_output_sources MODIFY COLUMN source_type ENUM('stock_box', 'parent_output') NOT NULL");
    }
};
