<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->foreignId('order_maritime_container_id')->nullable()->after('order_id')
                ->constrained('order_maritime_containers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_maritime_container_id');
        });
    }
};
