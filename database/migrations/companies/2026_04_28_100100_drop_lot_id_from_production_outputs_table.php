<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_outputs', function (Blueprint $table) {
            $table->dropIndex(['lot_id']);
            $table->dropColumn('lot_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_outputs', function (Blueprint $table) {
            $table->string('lot_id')->nullable()->comment('Lote como string (opcional)');
            $table->index('lot_id');
        });
    }
};
