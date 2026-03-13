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

        Schema::table('stores', function (Blueprint $table) {
            $table->enum('store_type', ['interno', 'externo'])->default('interno')->after('capacity');
            $table->foreignId('external_user_id')->nullable()->after('store_type')->constrained('external_users')->nullOnDelete();
            $table->index('store_type');
            $table->index('external_user_id');
        });
    }

    public function down(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['store_type']);
            $table->dropIndex(['external_user_id']);
            $table->dropConstrainedForeignId('external_user_id');
            $table->dropColumn('store_type');
        });
    }
};
