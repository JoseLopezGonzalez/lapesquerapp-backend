<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        if (! Schema::hasTable('production_output_sources')) {
            return;
        }

        if (! Schema::hasColumn('production_output_sources', 'contribution_percentage')) {
            return;
        }

        Schema::table('production_output_sources', function (Blueprint $table) {
            $table->dropColumn('contribution_percentage');
        });
    }

    public function down(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        if (! Schema::hasTable('production_output_sources')) {
            return;
        }

        if (Schema::hasColumn('production_output_sources', 'contribution_percentage')) {
            return;
        }

        Schema::table('production_output_sources', function (Blueprint $table) {
            $table->decimal('contribution_percentage', 5, 2)->nullable()->after('contributed_boxes');
        });
    }
};
