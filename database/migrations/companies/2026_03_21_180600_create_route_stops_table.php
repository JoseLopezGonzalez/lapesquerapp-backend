<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('route_template_stop_id')->nullable()->constrained('route_template_stops')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->string('stop_type', 32);
            $table->string('target_type', 32)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained('prospects')->nullOnDelete();
            $table->string('label')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('result', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['route_id', 'position']);
            $table->index(['customer_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('route_stop_id')
                ->references('id')
                ->on('route_stops')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['route_stop_id']);
        });

        Schema::dropIfExists('route_stops');
    }
};
