<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('suppliers', function (Blueprint $table) {
            $table->string('type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('suppliers', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
        });
    }
};
