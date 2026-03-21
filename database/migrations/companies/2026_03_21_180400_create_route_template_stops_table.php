<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_template_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_template_id')->constrained('route_templates')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('stop_type', 32);
            $table->string('target_type', 32)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained('prospects')->nullOnDelete();
            $table->string('label')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['route_template_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_template_stops');
    }
};
