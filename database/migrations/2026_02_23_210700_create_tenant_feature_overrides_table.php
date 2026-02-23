<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('tenant_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('flag_key', 100);
            $table->boolean('enabled')->default(false);
            $table->foreignId('overridden_by_superadmin_id')
                ->nullable()
                ->constrained('superadmin_users')
                ->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'flag_key']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('tenant_feature_overrides');
    }
};
