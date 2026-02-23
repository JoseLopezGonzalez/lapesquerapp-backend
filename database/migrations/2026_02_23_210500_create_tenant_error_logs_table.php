<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('tenant_error_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('tenants')
                ->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('error_class', 255)->nullable();
            $table->text('error_message');
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('tenant_error_logs');
    }
};
