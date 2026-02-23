<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('tenant_blocklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->enum('type', ['ip', 'email']);
            $table->string('value', 255);
            $table->foreignId('blocked_by_superadmin_id')
                ->nullable()
                ->constrained('superadmin_users')
                ->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'value']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('tenant_blocklists');
    }
};
