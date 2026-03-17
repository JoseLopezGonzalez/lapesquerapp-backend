<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salesperson_id')->nullable()->constrained('salespeople')->nullOnDelete();
            $table->string('company_name');
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->json('species_interest')->nullable();
            $table->string('origin', 32)->default('other');
            $table->string('status', 32)->default('new');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('next_action_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('commercial_interest_notes')->nullable();
            $table->dateTime('last_contact_at')->nullable();
            $table->dateTime('last_offer_at')->nullable();
            $table->text('lost_reason')->nullable();
            $table->timestamps();

            $table->index(['salesperson_id', 'status']);
            $table->index('next_action_at');
            $table->index('last_contact_at');
            $table->index('last_offer_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
