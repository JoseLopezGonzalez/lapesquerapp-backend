<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Añade columna timeline (JSON) para historial de cambios del palet (F-01).
     */
    public function up(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        if (! Schema::hasTable('pallets')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table) {
            if (! Schema::hasColumn('pallets', 'timeline')) {
                $table->json('timeline')->nullable()->after('reception_id');
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

        Schema::table('pallets', function (Blueprint $table) {
            if (Schema::hasColumn('pallets', 'timeline')) {
                $table->dropColumn('timeline');
            }
        });
    }
};
