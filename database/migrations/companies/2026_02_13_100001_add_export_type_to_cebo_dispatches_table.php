<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Añade export_type (facilcom / a3erp) usado por modelo y producción.
     */
    public function up(): void
    {
        Schema::table('cebo_dispatches', function (Blueprint $table) {
            $table->string('export_type')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cebo_dispatches', function (Blueprint $table) {
            $table->dropColumn('export_type');
        });
    }
};
