<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('tenant_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('triggered_by_superadmin_id')
                ->nullable()
                ->constrained('superadmin_users')
                ->nullOnDelete();
            $table->unsignedInteger('migrations_applied')->default(0);
            $table->text('output')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('tenant_migration_runs');
    }
};
