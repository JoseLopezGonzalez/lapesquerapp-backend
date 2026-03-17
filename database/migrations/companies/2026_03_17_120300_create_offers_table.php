<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->nullable()->constrained('prospects')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('salesperson_id')->constrained('salespeople')->cascadeOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('send_channel', 32)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('incoterm_id')->nullable()->constrained('incoterms')->nullOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained('payment_terms')->nullOnDelete();
            $table->string('currency', 3)->default('EUR');
            $table->text('notes')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->nullOnDelete();
            $table->timestamps();

            $table->index(['salesperson_id', 'status']);
            $table->index('order_id');
        });

        DB::statement('
            ALTER TABLE offers
            ADD CONSTRAINT chk_offers_exactly_one_target
            CHECK (
                (prospect_id IS NOT NULL AND customer_id IS NULL)
                OR (prospect_id IS NULL AND customer_id IS NOT NULL)
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
