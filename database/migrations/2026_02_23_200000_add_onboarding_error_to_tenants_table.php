<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('tenants', function (Blueprint $table) {
            $table->text('onboarding_error')->nullable()->after('onboarding_step');
            $table->timestamp('onboarding_failed_at')->nullable()->after('onboarding_error');
        });

        // Pre-existing active tenants already have a working database;
        // mark them as onboarding-complete so guards don't block them.
        DB::connection('mysql')
            ->table('tenants')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('onboarding_step')->orWhere('onboarding_step', 0);
            })
            ->update(['onboarding_step' => 8]);
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('tenants', function (Blueprint $table) {
            $table->dropColumn(['onboarding_error', 'onboarding_failed_at']);
        });
    }
};
