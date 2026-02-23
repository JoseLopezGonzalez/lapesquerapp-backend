<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('flag_key', 100);
            $table->enum('plan', ['basic', 'pro', 'enterprise'])->default('basic');
            $table->boolean('enabled')->default(false);
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['flag_key', 'plan']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('feature_flags');
    }
};
