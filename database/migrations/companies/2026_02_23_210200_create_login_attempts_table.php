<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('attempted_at')->useCurrent();

            $table->index(['email', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('login_attempts');
    }
};
