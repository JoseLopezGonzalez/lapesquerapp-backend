<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_template_id')->nullable()->constrained('route_templates')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('route_date')->nullable();
            $table->string('status', 32)->default('planned');
            $table->foreignId('salesperson_id')->nullable()->constrained('salespeople')->nullOnDelete();
            $table->foreignId('field_operator_id')->nullable()->constrained('field_operators')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['field_operator_id', 'route_date']);
            $table->index(['salesperson_id', 'route_date']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('route_id')
                ->references('id')
                ->on('routes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['route_id']);
        });

        Schema::dropIfExists('routes');
    }
};
