<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysql')->create('impersonation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('superadmin_user_id')->constrained('superadmin_users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('target_user_id');
            $table->enum('mode', ['consent', 'silent']);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'superadmin_user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('impersonation_logs');
    }
};
