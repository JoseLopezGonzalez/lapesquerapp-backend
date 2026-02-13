<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Añade columnas usadas por modelo/API para exportación cebo (facilcom / a3erp).
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('cebo_export_type')->nullable()->after('facil_com_code');
            $table->string('a3erp_cebo_code')->nullable()->after('cebo_export_type');
            $table->string('facilcom_cebo_code')->nullable()->after('a3erp_cebo_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['cebo_export_type', 'a3erp_cebo_code', 'facilcom_cebo_code']);
        });
    }
};
