<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('maquilador_destination')->nullable()->after('external_processor_id');
            $table->text('loading_address')->nullable()->after('maquilador_destination');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['maquilador_destination', 'loading_address']);
        });
    }
};
