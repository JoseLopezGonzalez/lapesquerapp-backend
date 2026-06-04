<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pallets')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table) {
            if (! Schema::hasColumn('pallets', 'pallet_tare_weight_kg')) {
                $table->decimal('pallet_tare_weight_kg', 10, 3)->nullable()->after('observations');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pallets')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table) {
            if (Schema::hasColumn('pallets', 'pallet_tare_weight_kg')) {
                $table->dropColumn('pallet_tare_weight_kg');
            }
        });
    }
};
