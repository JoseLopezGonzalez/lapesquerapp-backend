<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('impersonation_logs', function (Blueprint $table) {
            $table->string('reason', 500)->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('impersonation_logs', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
