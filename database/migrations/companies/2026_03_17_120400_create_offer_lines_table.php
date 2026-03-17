<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 32);
            $table->decimal('unit_price', 10, 4);
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->integer('boxes')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('created_at')->useCurrent();

            $table->index('offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_lines');
    }
};
