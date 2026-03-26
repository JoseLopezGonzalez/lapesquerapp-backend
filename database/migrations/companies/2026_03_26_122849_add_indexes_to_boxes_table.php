<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('boxes', function (Blueprint $table) {
            $table->index('lot', 'boxes_lot_index');
            $table->index('gs1_128', 'boxes_gs1_128_index');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('boxes', function (Blueprint $table) {
            $table->dropIndex('boxes_lot_index');
            $table->dropIndex('boxes_gs1_128_index');
        });
    }
};
