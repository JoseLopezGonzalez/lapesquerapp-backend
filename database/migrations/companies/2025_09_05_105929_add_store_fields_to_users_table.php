<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_store_id')->nullable()->after('email_verified_at');
            $table->string('company_name')->nullable()->after('assigned_store_id');
            $table->string('company_logo_url')->nullable()->after('company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['assigned_store_id', 'company_name', 'company_logo_url']);
        });
    }
};
