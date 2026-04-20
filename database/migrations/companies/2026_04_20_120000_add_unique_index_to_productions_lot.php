<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('productions') || ! Schema::hasColumn('productions', 'lot')) {
            return;
        }

        $hasUniqueLotIndex = collect(DB::select("SHOW INDEX FROM productions WHERE Key_name = 'productions_lot_unique'"))
            ->isNotEmpty();

        if ($hasUniqueLotIndex) {
            return;
        }

        Schema::table('productions', function (Blueprint $table) {
            $table->unique('lot', 'productions_lot_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('productions')) {
            return;
        }

        Schema::table('productions', function (Blueprint $table) {
            $table->dropUnique('productions_lot_unique');
        });
    }
};
