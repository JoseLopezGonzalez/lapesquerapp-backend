<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'onboarding_failed',
                'onboarding_stuck',
                'migrations_pending',
                'suspicious_activity',
                'impersonation_started',
                'queue_stopped',
            ]);
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('tenants')
                ->nullOnDelete();
            $table->string('message');
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_superadmin_id')
                ->nullable()
                ->constrained('superadmin_users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['severity', 'resolved_at']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('system_alerts');
    }
};
