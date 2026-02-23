<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysql')->table('tenants', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'suspended', 'cancelled'])
                ->default('active')
                ->after('database');
            $table->string('plan', 50)->nullable()->after('status');
            $table->date('renewal_at')->nullable()->after('plan');
            $table->string('timezone', 50)->default('Europe/Madrid')->after('renewal_at');
            $table->timestamp('last_activity_at')->nullable()->after('timezone');
            $table->tinyInteger('onboarding_step')->unsigned()->nullable()->after('last_activity_at');
            $table->string('admin_email')->nullable()->after('onboarding_step');

            $table->index('status', 'idx_tenants_status');
        });

        DB::connection('mysql')->statement("
            UPDATE tenants SET status = CASE WHEN active = 1 THEN 'active' ELSE 'suspended' END
        ");

        Schema::connection('mysql')->table('tenants', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('tenants', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('database');
        });

        DB::connection('mysql')->statement("
            UPDATE tenants SET active = CASE WHEN status = 'active' THEN 1 ELSE 0 END
        ");

        Schema::connection('mysql')->table('tenants', function (Blueprint $table) {
            $table->dropIndex('idx_tenants_status');
            $table->dropColumn([
                'status', 'plan', 'renewal_at', 'timezone',
                'last_activity_at', 'onboarding_step', 'admin_email',
            ]);
        });
    }
};
